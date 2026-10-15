using System.Globalization;
using Microsoft.Data.Sqlite;

namespace Sidrena.Core;

public sealed record PriceItem(
    long Id, string Name, string Kind, string Sku, string Barcode, string Unit,
    long CurrentCents, long? AnchorCents, string? AnchorDate, string? AnchorSource,
    DateTimeOffset CreatedUtc);

public sealed record PriceChange(long Id, long ItemId, long PreviousCents, long CurrentCents, string Reason, DateTimeOffset ChangedUtc);

/// <summary>Local-first catalogue. Historical anchor prices are never inferred from current prices.</summary>
public sealed class PriceStore : IDisposable
{
    private readonly SqliteConnection _connection;
    private bool _disposed;
    private static readonly HashSet<string> PermittedAnchorDates = new(StringComparer.Ordinal)
        { "2026-09-10", "2025-05-02" };

    public PriceStore(string databasePath)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(databasePath);
        var fullPath = Path.GetFullPath(databasePath);
        Directory.CreateDirectory(Path.GetDirectoryName(fullPath)!);
        _connection = new SqliteConnection(new SqliteConnectionStringBuilder
        {
            DataSource = fullPath,
            Mode = SqliteOpenMode.ReadWriteCreate,
            Cache = SqliteCacheMode.Private
        }.ToString());
        _connection.Open();
        Execute("PRAGMA foreign_keys=ON");
        Execute("PRAGMA busy_timeout=5000");
        Execute("PRAGMA journal_mode=WAL");
        Execute("""
            CREATE TABLE IF NOT EXISTS price_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL CHECK(length(trim(name)) > 0),
                kind TEXT NOT NULL CHECK(kind IN ('Proizvod','Usluga')),
                sku TEXT NOT NULL DEFAULT '',
                barcode TEXT NOT NULL DEFAULT '',
                unit TEXT NOT NULL DEFAULT '',
                current_cents INTEGER NOT NULL CHECK(current_cents >= 0),
                anchor_cents INTEGER CHECK(anchor_cents >= 0),
                anchor_date TEXT,
                anchor_source TEXT,
                created_utc TEXT NOT NULL,
                CHECK((anchor_cents IS NULL AND anchor_date IS NULL AND anchor_source IS NULL)
                    OR (anchor_cents IS NOT NULL AND anchor_date IS NOT NULL AND anchor_source IS NOT NULL))
            )
            """);
        Execute("""
            CREATE TABLE IF NOT EXISTS price_changes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_id INTEGER NOT NULL REFERENCES price_items(id) ON DELETE RESTRICT,
                previous_cents INTEGER NOT NULL,
                current_cents INTEGER NOT NULL,
                reason TEXT NOT NULL CHECK(length(trim(reason)) > 0),
                changed_utc TEXT NOT NULL
            )
            """);
        Execute("CREATE INDEX IF NOT EXISTS idx_changes_item_time ON price_changes(item_id, id DESC)");
    }

    public long AddItem(string name, string kind, string sku, string barcode, string unit,
        decimal currentPrice, decimal? anchorPrice, string? anchorDate, string? anchorSource)
    {
        if (string.IsNullOrWhiteSpace(name)) throw new ArgumentException("Naziv je obvezan.", nameof(name));
        if (kind is not ("Proizvod" or "Usluga")) throw new ArgumentException("Nepoznata vrsta stavke.", nameof(kind));
        if (anchorPrice.HasValue)
        {
            if (anchorDate is null || !PermittedAnchorDates.Contains(anchorDate))
                throw new ArgumentException("Odaberite propisani datum i provjerite njegovu primjenjivost.", nameof(anchorDate));
            if (string.IsNullOrWhiteSpace(anchorSource))
                throw new ArgumentException("Zapišite izvor povijesne sidrene cijene.", nameof(anchorSource));
        }
        else if (anchorDate is not null || !string.IsNullOrWhiteSpace(anchorSource))
            throw new ArgumentException("Sidreni datum i izvor zahtijevaju potvrđen povijesni iznos.");

        using var cmd = _connection.CreateCommand();
        cmd.CommandText = """
            INSERT INTO price_items (name,kind,sku,barcode,unit,current_cents,anchor_cents,anchor_date,anchor_source,created_utc)
            VALUES ($name,$kind,$sku,$barcode,$unit,$current,$anchor,$date,$source,$created);
            SELECT last_insert_rowid();
            """;
        cmd.Parameters.AddWithValue("$name", name.Trim());
        cmd.Parameters.AddWithValue("$kind", kind);
        cmd.Parameters.AddWithValue("$sku", sku.Trim());
        cmd.Parameters.AddWithValue("$barcode", barcode.Trim());
        cmd.Parameters.AddWithValue("$unit", unit.Trim());
        cmd.Parameters.AddWithValue("$current", ToCents(currentPrice));
        cmd.Parameters.AddWithValue("$anchor", anchorPrice.HasValue ? ToCents(anchorPrice.Value) : DBNull.Value);
        cmd.Parameters.AddWithValue("$date", (object?)anchorDate ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$source", (object?)anchorSource?.Trim() ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$created", DateTimeOffset.UtcNow.ToString("O", CultureInfo.InvariantCulture));
        return (long)(cmd.ExecuteScalar() ?? throw new InvalidOperationException("Unos nije uspio."));
    }

    public void UpdateCurrentPrice(long id, decimal newPrice, string reason)
    {
        if (string.IsNullOrWhiteSpace(reason)) throw new ArgumentException("Razlog izmjene je obvezan.", nameof(reason));
        long amount = ToCents(newPrice);
        using var tx = _connection.BeginTransaction();
        using var read = _connection.CreateCommand();
        read.Transaction = tx;
        read.CommandText = "SELECT current_cents FROM price_items WHERE id=$id";
        read.Parameters.AddWithValue("$id", id);
        var current = read.ExecuteScalar();
        if (current is null) throw new KeyNotFoundException("Stavka nije pronađena.");
        long previous = (long)current;
        if (previous == amount) { tx.Commit(); return; }

        using var update = _connection.CreateCommand();
        update.Transaction = tx;
        update.CommandText = "UPDATE price_items SET current_cents=$amount WHERE id=$id";
        update.Parameters.AddWithValue("$amount", amount);
        update.Parameters.AddWithValue("$id", id);
        if (update.ExecuteNonQuery() != 1) throw new InvalidOperationException("Izmjena nije uspjela.");

        using var audit = _connection.CreateCommand();
        audit.Transaction = tx;
        audit.CommandText = """
            INSERT INTO price_changes (item_id,previous_cents,current_cents,reason,changed_utc)
            VALUES ($id,$old,$new,$reason,$time)
            """;
        audit.Parameters.AddWithValue("$id", id);
        audit.Parameters.AddWithValue("$old", previous);
        audit.Parameters.AddWithValue("$new", amount);
        audit.Parameters.AddWithValue("$reason", reason.Trim());
        audit.Parameters.AddWithValue("$time", DateTimeOffset.UtcNow.ToString("O", CultureInfo.InvariantCulture));
        audit.ExecuteNonQuery();
        tx.Commit();
    }

    public IReadOnlyList<PriceItem> ListItems()
    {
        using var cmd = _connection.CreateCommand();
        cmd.CommandText = """
            SELECT id,name,kind,sku,barcode,unit,current_cents,anchor_cents,anchor_date,anchor_source,created_utc
            FROM price_items ORDER BY name COLLATE NOCASE, id
            """;
        using var reader = cmd.ExecuteReader();
        var result = new List<PriceItem>();
        while (reader.Read())
            result.Add(new PriceItem(
                reader.GetInt64(0), reader.GetString(1), reader.GetString(2),
                reader.GetString(3), reader.GetString(4), reader.GetString(5),
                reader.GetInt64(6), reader.IsDBNull(7) ? null : reader.GetInt64(7),
                reader.IsDBNull(8) ? null : reader.GetString(8),
                reader.IsDBNull(9) ? null : reader.GetString(9),
                DateTimeOffset.Parse(reader.GetString(10), CultureInfo.InvariantCulture)));
        return result;
    }

    public IReadOnlyList<PriceChange> ListChanges(long itemId)
    {
        using var cmd = _connection.CreateCommand();
        cmd.CommandText = """
            SELECT id,item_id,previous_cents,current_cents,reason,changed_utc
            FROM price_changes WHERE item_id=$id ORDER BY id DESC
            """;
        cmd.Parameters.AddWithValue("$id", itemId);
        using var reader = cmd.ExecuteReader();
        var result = new List<PriceChange>();
        while (reader.Read())
            result.Add(new PriceChange(reader.GetInt64(0), reader.GetInt64(1), reader.GetInt64(2),
                reader.GetInt64(3), reader.GetString(4), DateTimeOffset.Parse(reader.GetString(5), CultureInfo.InvariantCulture)));
        return result;
    }

    public static long ToCents(decimal euros)
    {
        if (euros < 0 || decimal.Round(euros, 2) != euros || euros > 90_000_000_000m)
            throw new ArgumentOutOfRangeException(nameof(euros), "Unesite pozitivan iznos s najviše dvije decimale.");
        return checked((long)(euros * 100m));
    }

    public static string FormatEur(long cents) => (cents / 100m).ToString("F2", CultureInfo.GetCultureInfo("hr-HR"));

    private void Execute(string sql)
    {
        using var cmd = _connection.CreateCommand();
        cmd.CommandText = sql;
        cmd.ExecuteNonQuery();
    }

    public void Dispose()
    {
        if (_disposed) return;
        _connection.Dispose();
        _disposed = true;
    }
}
