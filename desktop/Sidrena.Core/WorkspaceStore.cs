using System.Globalization;
using Microsoft.Data.Sqlite;

namespace Sidrena.Core;

public sealed record BusinessLocation(long Id, string Name, string Address, string City, bool IsActive);
public sealed record DraftPublication(long Id, string FilePath, string Format, int ItemCount, string Sha256, DateTimeOffset CreatedUtc);
public sealed record PriceAuditView(long Id, string ItemName, string ItemKind, long PreviousCents, long CurrentCents, string Reason, DateTimeOffset ChangedUtc);

/// <summary>
/// Locally persisted workspace metadata. Recorded exports are drafts, never asserted as public publications.
/// </summary>
public sealed class WorkspaceStore : IDisposable
{
    private readonly SqliteConnection _connection;
    public WorkspaceStore(string databasePath)
    {
        var full = Path.GetFullPath(databasePath);
        Directory.CreateDirectory(Path.GetDirectoryName(full)!);
        _connection = new SqliteConnection(new SqliteConnectionStringBuilder {
            DataSource = full, Mode = SqliteOpenMode.ReadWriteCreate, Cache = SqliteCacheMode.Private
        }.ToString());
        _connection.Open();
        Execute("PRAGMA busy_timeout=5000");
        Execute("PRAGMA journal_mode=WAL");
        Execute("PRAGMA foreign_keys=ON");
        Execute("""
            CREATE TABLE IF NOT EXISTS desktop_locations(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL CHECK(length(trim(name)) > 0),
                address TEXT NOT NULL DEFAULT '',
                city TEXT NOT NULL DEFAULT '',
                active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1))
            )
            """);
        Execute("""
            CREATE TABLE IF NOT EXISTS desktop_draft_exports(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                file_path TEXT NOT NULL,
                format TEXT NOT NULL CHECK(format IN ('CSV','XML')),
                item_count INTEGER NOT NULL CHECK(item_count>=0),
                sha256 TEXT NOT NULL,
                created_utc TEXT NOT NULL
            )
            """);
        Execute("CREATE INDEX IF NOT EXISTS idx_desktop_exports_date ON desktop_draft_exports(id DESC)");
    }

    public long AddLocation(string name, string address, string city)
    {
        if (string.IsNullOrWhiteSpace(name)) throw new ArgumentException("Naziv lokacije je obvezan.", nameof(name));
        using var c = _connection.CreateCommand();
        c.CommandText = "INSERT INTO desktop_locations(name,address,city) VALUES($n,$a,$c); SELECT last_insert_rowid();";
        c.Parameters.AddWithValue("$n", name.Trim());
        c.Parameters.AddWithValue("$a", address.Trim());
        c.Parameters.AddWithValue("$c", city.Trim());
        return (long)(c.ExecuteScalar() ?? throw new InvalidOperationException("Lokacija nije spremljena."));
    }

    public IReadOnlyList<BusinessLocation> ListLocations()
    {
        using var c = _connection.CreateCommand();
        c.CommandText = "SELECT id,name,address,city,active FROM desktop_locations ORDER BY name COLLATE NOCASE,id";
        using var r = c.ExecuteReader();
        var result = new List<BusinessLocation>();
        while(r.Read()) result.Add(new BusinessLocation(r.GetInt64(0),r.GetString(1),r.GetString(2),r.GetString(3),r.GetInt64(4)==1));
        return result;
    }

    public void SetLocationActive(long id, bool active)
    {
        using var c = _connection.CreateCommand();
        c.CommandText = "UPDATE desktop_locations SET active=$active WHERE id=$id";
        c.Parameters.AddWithValue("$active",active ? 1 : 0);
        c.Parameters.AddWithValue("$id",id);
        if(c.ExecuteNonQuery()!=1) throw new KeyNotFoundException("Lokacija nije pronađena.");
    }

    public void RecordDraftExport(string filePath, string format, int itemCount)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(filePath);
        if(format is not ("CSV" or "XML")) throw new ArgumentException("Nepoznat format.", nameof(format));
        if(itemCount<0) throw new ArgumentOutOfRangeException(nameof(itemCount));
        string full = Path.GetFullPath(filePath);
        using var stream = File.OpenRead(full);
        string hash = Convert.ToHexString(System.Security.Cryptography.SHA256.HashData(stream));
        using var c = _connection.CreateCommand();
        c.CommandText = """
            INSERT INTO desktop_draft_exports(file_path,format,item_count,sha256,created_utc)
            VALUES($p,$f,$n,$hash,$date)
            """;
        c.Parameters.AddWithValue("$p",full);
        c.Parameters.AddWithValue("$f",format);
        c.Parameters.AddWithValue("$n",itemCount);
        c.Parameters.AddWithValue("$hash",hash);
        c.Parameters.AddWithValue("$date",DateTimeOffset.UtcNow.ToString("O",CultureInfo.InvariantCulture));
        c.ExecuteNonQuery();
    }

    public IReadOnlyList<DraftPublication> ListDraftExports()
    {
        using var c = _connection.CreateCommand();
        c.CommandText = "SELECT id,file_path,format,item_count,sha256,created_utc FROM desktop_draft_exports ORDER BY id DESC LIMIT 500";
        using var r = c.ExecuteReader();
        var result = new List<DraftPublication>();
        while(r.Read()) result.Add(new DraftPublication(r.GetInt64(0),r.GetString(1),r.GetString(2),r.GetInt32(3),r.GetString(4),
            DateTimeOffset.Parse(r.GetString(5),CultureInfo.InvariantCulture)));
        return result;
    }

    public IReadOnlyList<PriceAuditView> ListRecentChanges(int maxRows=500)
    {
        if(maxRows is < 1 or > 10000) throw new ArgumentOutOfRangeException(nameof(maxRows));
        using var c = _connection.CreateCommand();
        c.CommandText = """
            SELECT c.id,i.name,i.kind,c.previous_cents,c.current_cents,c.reason,c.changed_utc
            FROM price_changes c JOIN price_items i ON i.id=c.item_id ORDER BY c.id DESC LIMIT $max
            """;
        c.Parameters.AddWithValue("$max",maxRows);
        using var r = c.ExecuteReader();
        var result = new List<PriceAuditView>();
        while(r.Read()) result.Add(new PriceAuditView(r.GetInt64(0),r.GetString(1),r.GetString(2),r.GetInt64(3),
            r.GetInt64(4),r.GetString(5),DateTimeOffset.Parse(r.GetString(6),CultureInfo.InvariantCulture)));
        return result;
    }

    /// <summary>SQLite online backup includes committed WAL changes; destination is replaced only after success.</summary>
    public void BackupTo(string destination)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(destination);
        string path = Path.GetFullPath(destination);
        Directory.CreateDirectory(Path.GetDirectoryName(path)!);
        if(string.Equals(path,Path.GetFullPath(_connection.DataSource),StringComparison.OrdinalIgnoreCase))
            throw new ArgumentException("Sigurnosna kopija ne može zamijeniti aktivnu bazu.", nameof(destination));
        string temp = path+"."+Guid.NewGuid().ToString("N")+".tmp";
        try
        {
            using(var backup = new SqliteConnection(new SqliteConnectionStringBuilder {
                DataSource=temp,Mode=SqliteOpenMode.ReadWriteCreate,Pooling=false }.ToString()))
            {
                backup.Open();
                _connection.BackupDatabase(backup);
            }
            File.Move(temp,path,true);
        }
        finally { if(File.Exists(temp)) File.Delete(temp); }
    }

    private void Execute(string sql)
    {
        using var c = _connection.CreateCommand();
        c.CommandText=sql;
        c.ExecuteNonQuery();
    }

    public void Dispose() => _connection.Dispose();
}
