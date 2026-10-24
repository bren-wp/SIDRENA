using System.Globalization;
using Microsoft.Data.Sqlite;

namespace Sidrena.Core;

public sealed record PriceItem(
    long Id, string Name, string Kind, string Sku, string Barcode, string Unit,
    long CurrentCents, long? AnchorCents, string? AnchorDate, string? AnchorSource,
    DateTimeOffset CreatedUtc);

public sealed record PriceChange(long Id, long ItemId, long PreviousCents, long CurrentCents, string Reason, DateTimeOffset ChangedUtc);


public sealed record CatalogImportReview(int Total,int Products,int Services);
public sealed record ItemDetailsSnapshot(string Name,string Sku,string Barcode,string Unit);
public sealed record ItemMetadataChange(long Id,long ItemId,string BeforeJson,string AfterJson,string Reason,DateTimeOffset ChangedUtc);

/// <summary>Local-first catalogue. Historical anchor prices are never inferred from current prices.</summary>
public sealed class PriceStore : IDisposable
{
    private readonly SqliteConnection _connection;
    private bool _disposed;
    private static readonly HashSet<string> PermittedAnchorDates =
        new(SidrenaLegalContract.KnownReferenceDates,StringComparer.Ordinal);

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
        Execute("""
            CREATE TABLE IF NOT EXISTS item_metadata_changes(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_id INTEGER NOT NULL REFERENCES price_items(id) ON DELETE RESTRICT,
                before_json TEXT NOT NULL,
                after_json TEXT NOT NULL,
                reason TEXT NOT NULL CHECK(length(trim(reason)) > 0),
                changed_utc TEXT NOT NULL
            )
            """);
        Execute("CREATE INDEX IF NOT EXISTS idx_item_metadata_changes ON item_metadata_changes(item_id,id DESC)");
    }

    public long AddItem(string name, string kind, string sku, string barcode, string unit,
        decimal currentPrice, decimal? anchorPrice, string? anchorDate, string? anchorSource)
    {
        ValidateDetails(name,sku,barcode,unit);
        ValidateAnchor(kind,anchorPrice,anchorDate,anchorSource);

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


    public IReadOnlyList<ItemMetadataChange> ListMetadataChanges(long itemId)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = """
            SELECT id,item_id,before_json,after_json,reason,changed_utc
            FROM item_metadata_changes WHERE item_id=$id ORDER BY id DESC
            """;
        command.Parameters.AddWithValue("$id",itemId);
        using var reader=command.ExecuteReader();
        var history=new List<ItemMetadataChange>();
        while(reader.Read())
            history.Add(new ItemMetadataChange(reader.GetInt64(0),reader.GetInt64(1),
                reader.GetString(2),reader.GetString(3),reader.GetString(4),
                DateTimeOffset.Parse(reader.GetString(5),CultureInfo.InvariantCulture)));
        return history;
    }

    /// <summary>Edit descriptive metadata only; historical anchors and current prices cannot be overwritten here.</summary>
    public void UpdateItemDetails(long id, string name, string sku, string barcode, string unit, string reason)
    {
        ValidateDetails(name,sku,barcode,unit);
        if(string.IsNullOrWhiteSpace(reason))throw new ArgumentException("Razlog izmjene je obvezan.",nameof(reason));
        using var transaction=_connection.BeginTransaction();
        using var read=_connection.CreateCommand();
        read.Transaction=transaction;
        read.CommandText="SELECT name,kind,sku,barcode,unit FROM price_items WHERE id=$id";
        read.Parameters.AddWithValue("$id",id);
        string kind,oldName,oldSku,oldBarcode,oldUnit;
        using(var reader=read.ExecuteReader())
        {
            if(!reader.Read())throw new KeyNotFoundException("Stavka nije pronađena.");
            oldName=reader.GetString(0);kind=reader.GetString(1);oldSku=reader.GetString(2);
            oldBarcode=reader.GetString(3);oldUnit=reader.GetString(4);
        }
        var next=new ItemDetailsSnapshot(name.Trim(),sku.Trim(),barcode.Trim(),unit.Trim());
        var previous=new ItemDetailsSnapshot(oldName,oldSku,oldBarcode,oldUnit);
        if(previous==next){transaction.Commit();return;}
        if(!string.IsNullOrWhiteSpace(next.Sku))
        {
            using var collision=_connection.CreateCommand();
            collision.Transaction=transaction;
            collision.CommandText="SELECT count(*) FROM price_items WHERE id<>$id AND kind=$kind AND sku=$sku COLLATE NOCASE";
            collision.Parameters.AddWithValue("$id",id);
            collision.Parameters.AddWithValue("$kind",kind);
            collision.Parameters.AddWithValue("$sku",next.Sku);
            if((long)(collision.ExecuteScalar()??0L)>0)
                throw new ArgumentException("Šifra već postoji za istu vrstu stavke.",nameof(sku));
        }
        using var update=_connection.CreateCommand();
        update.Transaction=transaction;
        update.CommandText="UPDATE price_items SET name=$name,sku=$sku,barcode=$barcode,unit=$unit WHERE id=$id";
        update.Parameters.AddWithValue("$id",id);
        update.Parameters.AddWithValue("$name",next.Name);
        update.Parameters.AddWithValue("$sku",next.Sku);
        update.Parameters.AddWithValue("$barcode",next.Barcode);
        update.Parameters.AddWithValue("$unit",next.Unit);
        if(update.ExecuteNonQuery()!=1)throw new InvalidOperationException("Stavka nije ažurirana.");
        using var audit=_connection.CreateCommand();
        audit.Transaction=transaction;
        audit.CommandText="""
            INSERT INTO item_metadata_changes(item_id,before_json,after_json,reason,changed_utc)
            VALUES($id,$before,$after,$reason,$time)
            """;
        audit.Parameters.AddWithValue("$id",id);
        audit.Parameters.AddWithValue("$before",System.Text.Json.JsonSerializer.Serialize(previous));
        audit.Parameters.AddWithValue("$after",System.Text.Json.JsonSerializer.Serialize(next));
        audit.Parameters.AddWithValue("$reason",reason.Trim());
        audit.Parameters.AddWithValue("$time",DateTimeOffset.UtcNow.ToString("O",CultureInfo.InvariantCulture));
        audit.ExecuteNonQuery();
        transaction.Commit();
    }

    /// <summary>Validate and preview a batch without inserting or changing prices.</summary>
    public CatalogImportReview ReviewImportRows(IReadOnlyList<CatalogImportRow> rows)
        => InspectImportRows(rows,null);

    private CatalogImportReview InspectImportRows(IReadOnlyList<CatalogImportRow> rows,SqliteTransaction? transaction)
    {
        ArgumentNullException.ThrowIfNull(rows);
        if(rows.Count==0)throw new ArgumentException("Datoteka nema stavki za uvoz.",nameof(rows));
        if(rows.Count>5000)throw new ArgumentException("Najviše 5000 stavki po uvozu.",nameof(rows));
        var known=new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        using(var lookup=_connection.CreateCommand())
        {
            lookup.Transaction=transaction;
            lookup.CommandText="SELECT kind,sku FROM price_items WHERE trim(sku)<>''";
            using var reader=lookup.ExecuteReader();
            while(reader.Read())
                known.Add("S\u001f"+reader.GetString(0)+"\u001f"+reader.GetString(1).Trim());
        }
        using(var unnamed=_connection.CreateCommand())
        {
            unnamed.Transaction=transaction;
            unnamed.CommandText="SELECT kind,name FROM price_items WHERE trim(sku)=''";
            using var reader=unnamed.ExecuteReader();
            while(reader.Read())known.Add("N\u001f"+reader.GetString(0)+"\u001f"+reader.GetString(1).Trim());
        }
        int products=0,services=0;
        foreach(var row in rows)
        {
            ValidateDetails(row.Name,row.Sku,row.Barcode,row.Unit);
            ValidateAnchor(row.Kind,row.AnchorPrice,row.AnchorDate,row.AnchorSource);
            ToCents(row.CurrentPrice);
            if(row.AnchorPrice.HasValue)ToCents(row.AnchorPrice.Value);
            if(row.Kind=="Proizvod")products++;else services++;
            if(!string.IsNullOrWhiteSpace(row.Sku)&&!known.Add("S\u001f"+row.Kind+"\u001f"+row.Sku.Trim()))
                throw new ArgumentException($"Sukob šifre {row.Sku}: ništa nije uvezeno.");
            if(string.IsNullOrWhiteSpace(row.Sku)&&!known.Add("N\u001f"+row.Kind+"\u001f"+row.Name.Trim()))
                throw new ArgumentException($"Ponavlja se naziv bez šifre: {row.Name}. Uvoz je zaustavljen.");
        }
        return new CatalogImportReview(rows.Count,products,services);
    }

    /// <summary>All-or-nothing CSV import. Reject collisions while holding the write transaction.</summary>
    public int ImportNewRows(IReadOnlyList<CatalogImportRow> rows)
    {
        using var transaction=_connection.BeginTransaction();
        InspectImportRows(rows,transaction);
        foreach(var row in rows)
        {
            using var command=_connection.CreateCommand();
            command.Transaction=transaction;
            command.CommandText="""
                INSERT INTO price_items(name,kind,sku,barcode,unit,current_cents,anchor_cents,anchor_date,anchor_source,created_utc)
                VALUES($name,$kind,$sku,$barcode,$unit,$current,$anchor,$date,$source,$created)
                """;
            command.Parameters.AddWithValue("$name",row.Name.Trim());
            command.Parameters.AddWithValue("$kind",row.Kind);
            command.Parameters.AddWithValue("$sku",row.Sku.Trim());
            command.Parameters.AddWithValue("$barcode",row.Barcode.Trim());
            command.Parameters.AddWithValue("$unit",row.Unit.Trim());
            command.Parameters.AddWithValue("$current",ToCents(row.CurrentPrice));
            command.Parameters.AddWithValue("$anchor",row.AnchorPrice.HasValue?ToCents(row.AnchorPrice.Value):DBNull.Value);
            command.Parameters.AddWithValue("$date",(object?)row.AnchorDate??DBNull.Value);
            command.Parameters.AddWithValue("$source",(object?)row.AnchorSource?.Trim()??DBNull.Value);
            command.Parameters.AddWithValue("$created",DateTimeOffset.UtcNow.ToString("O",CultureInfo.InvariantCulture));
            command.ExecuteNonQuery();
        }
        transaction.Commit();
        return rows.Count;
    }

    private static void ValidateDetails(string name,string sku,string barcode,string unit)
    {
        if(string.IsNullOrWhiteSpace(name)||name.Trim().Length>240)
            throw new ArgumentException("Naziv mora sadržavati 1 do 240 znakova.",nameof(name));
        if(sku is null || sku.Length>100 || barcode is null || barcode.Length>100 || unit is null || unit.Length>64)
            throw new ArgumentException("Šifra, barkod ili jedinica mjere su predugi.");
        if(name.Any(c=>char.IsControl(c))||sku.Any(c=>char.IsControl(c))||
           barcode.Any(c=>char.IsControl(c))||unit.Any(c=>char.IsControl(c)))
            throw new ArgumentException("Kontrolni znakovi nisu dopušteni.");
    }
    private static void ValidateAnchor(string kind,decimal? price,string? date,string? source)
    {
        if(kind is not ("Proizvod" or "Usluga"))throw new ArgumentException("Nepoznata vrsta stavke.");
        if(price.HasValue)
        {
            if(date is null || !PermittedAnchorDates.Contains(date))
                throw new ArgumentException("Sidrena cijena zahtijeva dopušten referentni datum.");
            if(string.IsNullOrWhiteSpace(source))
                throw new ArgumentException("Za sidrenu cijenu potreban je dokumentirani izvor.");
            if(source.Trim().Length>500||source.Any(c=>char.IsControl(c)&&c!='\t'))
                throw new ArgumentException("Izvor sidrene cijene sadrži nedopuštene znakove ili je predug.");
        }
        else if(date is not null || !string.IsNullOrWhiteSpace(source))
            throw new ArgumentException("Datum i izvor bez sidrene cijene nisu dopušteni.");
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

    public (long Total,long ConfirmedAnchors) GetCatalogueTotals()
    {
        using var cmd=_connection.CreateCommand();
        cmd.CommandText="SELECT COUNT(*),COUNT(anchor_cents) FROM price_items";
        using var r=cmd.ExecuteReader();
        if(!r.Read())return (0,0);
        return (r.GetInt64(0),r.GetInt64(1));
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
