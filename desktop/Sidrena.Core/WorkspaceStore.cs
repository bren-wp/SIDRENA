using System.Globalization;
using Microsoft.Data.Sqlite;

namespace Sidrena.Core;

public sealed record BusinessLocation(long Id, string Name, string Address, string City, bool IsActive);
public sealed record BackupInspection(long Bytes,long Products,long Services,long PriceChanges,long Locations);
public sealed record DraftPublication(long Id, string FilePath, string Format, int ItemCount, string Sha256, DateTimeOffset CreatedUtc);
public sealed record PriceAuditView(long Id, long ItemId, string ItemName, string ItemKind, long PreviousCents, long CurrentCents, string Reason, DateTimeOffset ChangedUtc);

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
        Execute("""
            CREATE TABLE IF NOT EXISTS desktop_preferences(
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT NOT NULL
            )
            """);
    }

    /// <summary>Remember public site URL only; no passwords, tokens or catalog data are transmitted.</summary>
    public void SaveOfficialSite(string url)
    {
        OfficialPluginDiscovery.Endpoint(url);
        using var command=_connection.CreateCommand();
        command.CommandText="""
            INSERT INTO desktop_preferences(setting_key,setting_value)
            VALUES('official_wp_site',$value)
            ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value
            """;
        command.Parameters.AddWithValue("$value",url.Trim());
        command.ExecuteNonQuery();
    }

    public string? GetOfficialSite()
    {
        using var command=_connection.CreateCommand();
        command.CommandText="SELECT setting_value FROM desktop_preferences WHERE setting_key='official_wp_site'";
        return command.ExecuteScalar() as string;
    }

    public void ClearOfficialSite()
    {
        using var command=_connection.CreateCommand();
        command.CommandText="DELETE FROM desktop_preferences WHERE setting_key='official_wp_site'";
        command.ExecuteNonQuery();
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

    /// <summary>Confirm that a local draft still matches the bytes recorded at export time.</summary>
    public bool VerifyDraftExport(long id)
    {
        using var command=_connection.CreateCommand();
        command.CommandText="SELECT file_path,sha256 FROM desktop_draft_exports WHERE id=$id";
        command.Parameters.AddWithValue("$id",id);
        using var reader=command.ExecuteReader();
        if(!reader.Read())throw new KeyNotFoundException("Izvoz nije pronađen u arhivi.");
        var path=reader.GetString(0);
        var expected=reader.GetString(1);
        if(!File.Exists(path))return false;
        using var file=File.OpenRead(path);
        var actual=Convert.ToHexString(System.Security.Cryptography.SHA256.HashData(file));
        return string.Equals(expected,actual,StringComparison.OrdinalIgnoreCase);
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
            SELECT c.id,c.item_id,i.name,i.kind,c.previous_cents,c.current_cents,c.reason,c.changed_utc
            FROM price_changes c JOIN price_items i ON i.id=c.item_id ORDER BY c.id DESC LIMIT $max
            """;
        c.Parameters.AddWithValue("$max",maxRows);
        using var r = c.ExecuteReader();
        var result = new List<PriceAuditView>();
        while(r.Read()) result.Add(new PriceAuditView(r.GetInt64(0),r.GetInt64(1),r.GetString(2),r.GetString(3),r.GetInt64(4),
            r.GetInt64(5),r.GetString(6),DateTimeOffset.Parse(r.GetString(7),CultureInfo.InvariantCulture)));
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

    /// <summary>
    /// Read-only validation of a local SIDRENA database. It never opens an untrusted backup
    /// in ReadWriteCreate mode, and never initializes missing schema on the backup.
    /// </summary>
    public static BackupInspection InspectBackup(string source)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(source);
        string file=Path.GetFullPath(source);
        var info=new FileInfo(file);
        if(!info.Exists || info.Length<512 || info.Length>2_000_000_000L)
            throw new InvalidDataException("Odabrana kopija ne postoji ili nije prihvatljiva SQLite baza (najviše 2 GB).");
        try
        {
            using var backup=new SqliteConnection(new SqliteConnectionStringBuilder{
                DataSource=file,Mode=SqliteOpenMode.ReadOnly,Pooling=false,Cache=SqliteCacheMode.Private
            }.ToString());
            backup.Open();
            long Count(string query)
            {
                using var cmd=backup.CreateCommand();
                cmd.CommandText=query;
                return Convert.ToInt64(cmd.ExecuteScalar(),CultureInfo.InvariantCulture);
            }
            using(var check=backup.CreateCommand())
            {
                check.CommandText="PRAGMA integrity_check";
                using var rows=check.ExecuteReader();
                if(!rows.Read() || rows.GetString(0)!="ok" || rows.Read())
                    throw new InvalidDataException("SQLite kopija nije ispravna (integrity_check).");
            }
            using(var check=backup.CreateCommand())
            {
                check.CommandText="PRAGMA foreign_key_check";
                using var rows=check.ExecuteReader();
                if(rows.Read())throw new InvalidDataException("Kopija sadrži prekinute relacije.");
            }
            using(var schema=backup.CreateCommand())
            {
                schema.CommandText="SELECT name FROM sqlite_master WHERE type='table' AND name IN "+
                    "('price_items','price_changes','item_metadata_changes','desktop_locations','desktop_draft_exports')";
                using var r=schema.ExecuteReader();
                var names=new HashSet<string>(StringComparer.Ordinal);
                while(r.Read())names.Add(r.GetString(0));
                if(!new[]{"price_items","price_changes","item_metadata_changes",
                          "desktop_locations","desktop_draft_exports"}.All(names.Contains))
                    throw new InvalidDataException("Datoteka nema očekivanu strukturu SIDRENA baze.");
            }
            var products=Count("SELECT COUNT(*) FROM price_items WHERE kind='Proizvod'");
            var services=Count("SELECT COUNT(*) FROM price_items WHERE kind='Usluga'");
            var changes=Count("SELECT COUNT(*) FROM price_changes");
            var locations=Count("SELECT COUNT(*) FROM desktop_locations");
            return new BackupInspection(info.Length,products,services,changes,locations);
        }
        catch(SqliteException ex)
        {
            throw new InvalidDataException("Neispravna ili nečitljiva SQLite sigurnosna kopija.",ex);
        }
    }

    /// <summary>
    /// Restore committed SQLite snapshot into the existing workspace connection.
    /// Caller MUST first close all other connections to the live database.
    /// Makes and validates a separate pre-restore safety copy, and attempts
    /// recovery from that copy if applying the requested snapshot fails.
    /// </summary>
    public string RestoreFrom(string source,string safetyCopyPath)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(source);
        ArgumentException.ThrowIfNullOrWhiteSpace(safetyCopyPath);
        var live=Path.GetFullPath(_connection.DataSource);
        var backup=Path.GetFullPath(source);
        var safety=Path.GetFullPath(safetyCopyPath);
        if(string.Equals(live,backup,StringComparison.OrdinalIgnoreCase)||
           string.Equals(live,safety,StringComparison.OrdinalIgnoreCase)||
           string.Equals(backup,safety,StringComparison.OrdinalIgnoreCase))
            throw new ArgumentException("Izvor, aktivna baza i zaštitna kopija moraju biti različite datoteke.");
        InspectBackup(backup);  // No mutation until input passes integrity and schema checks.
        BackupTo(safety);
        InspectBackup(safety);
        void Apply(string path)
        {
            using var original=new SqliteConnection(new SqliteConnectionStringBuilder{
                DataSource=path,Mode=SqliteOpenMode.ReadOnly,Pooling=false,Cache=SqliteCacheMode.Private
            }.ToString());
            original.Open();
            original.BackupDatabase(_connection);
        }
        try
        {
            Apply(backup);
            // PRAGMA integrity_check includes committed data copied from the snapshot.
            using var check=_connection.CreateCommand();
            check.CommandText="PRAGMA integrity_check";
            if(!string.Equals(check.ExecuteScalar() as string,"ok",StringComparison.Ordinal))
                throw new InvalidDataException("Vraćena baza nije prošla provjeru integriteta.");
        }
        catch(Exception restoreError)
        {
            try
            {
                Apply(safety);
            }
            catch(Exception recoveryError)
            {
                throw new AggregateException(
                    "Povrat nije uspio i automatsko vraćanje prethodne baze također nije uspjelo. "+
                    "Sačuvana zaštitna kopija: "+safety,restoreError,recoveryError);
            }
            throw new InvalidOperationException(
                "Povrat nije uspio. Prethodni podaci vraćeni su iz zaštitne kopije: "+safety,restoreError);
        }
        return safety;
    }

    private void Execute(string sql)
    {
        using var c = _connection.CreateCommand();
        c.CommandText=sql;
        c.ExecuteNonQuery();
    }

    public void Dispose() => _connection.Dispose();
}
