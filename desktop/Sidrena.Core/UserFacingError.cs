using System.Net.Http;
using System.Text;

namespace Sidrena.Core;

/// <summary>
/// Stable, localized end-user diagnostics. Never include raw database, HTTP or
/// filesystem exception messages, which can contain user paths and SQL internals.
/// </summary>
public static class UserFacingError
{
    public static string Describe(Exception error)
    {
        ArgumentNullException.ThrowIfNull(error);
        return error switch
        {
            AggregateException => "Vraćanje podataka nije dovršeno. Sačuvajte sigurnosne kopije i obratite se podršci prije novih promjena.",
            UnauthorizedAccessException => "Nemate dopuštenje za ovu radnju. Provjerite prava pristupa odabranoj datoteci.",
            FileNotFoundException or DirectoryNotFoundException => "Odabrana datoteka više nije dostupna. Provjerite nalazi li se na očekivanom mjestu.",
            IOException => "Datoteci se trenutačno ne može pristupiti. Zatvorite druge programe koji je možda koriste i pokušajte ponovno.",
            Microsoft.Data.Sqlite.SqliteException => "Spremanje ili čitanje podataka nije uspjelo. Pokušajte ponovno; ako se problem ponavlja, obratite se podršci.",
            HttpRequestException => "Nije moguće povezati se s web-stranicom. Provjerite adresu i internetsku vezu.",
            TaskCanceledException or OperationCanceledException => "Provjera nije dovršena na vrijeme. Pokušajte ponovno.",
            InvalidDataException or System.Text.Json.JsonException or DecoderFallbackException => "Datoteka ili primljeni podaci nisu u podržanom formatu.",
            ArgumentException or FormatException or OverflowException => "Provjerite unesene podatke i pokušajte ponovno.",
            KeyNotFoundException => "Odabrana stavka više nije dostupna. Osvježite prikaz.",
            InvalidOperationException => "Radnja nije mogla biti dovršena. Provjerite podatke i pokušajte ponovno.",
            _ => "Dogodila se neočekivana pogreška. Ako se problem ponavlja, obratite se podršci."
        };
    }
}

/// <summary>
/// Gate ShellExecute to document formats deliberately produced by the app.
/// Do not launch an executable or script if the local archive metadata is altered.
/// </summary>
public static class DocumentOpenPolicy
{
    private static readonly HashSet<string> Allowed=new(StringComparer.OrdinalIgnoreCase)
        {".csv",".xml",".html"};

    public static string Validate(string path)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(path);
        if(path.IndexOf('\0')>=0)
            throw new ArgumentException("Nevaljana datoteka.",nameof(path));
        string full=Path.GetFullPath(path);
        if(!Allowed.Contains(Path.GetExtension(full)))
            throw new InvalidDataException("Ovaj format datoteke nije podržan za otvaranje.");
        if(!File.Exists(full))
            throw new FileNotFoundException("Datoteka nije pronađena.");
        return full;
    }
}
