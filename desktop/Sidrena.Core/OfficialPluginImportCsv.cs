using System.Text;

namespace Sidrena.Core;

/// <summary>
/// Deliberately staged CSV for the authenticated WordPress SIDRENA import flow.
/// The merchant must review and confirm mappings in wp-admin. It is not a
/// publication feed and does not write to WordPress automatically.
/// </summary>
public static class OfficialPluginImportCsv
{
    public const string Header =
        "naziv;sku;vrsta;marka;url;cijena_redovna;cijena_akcijska;" +
        "naziv_posebnog_oblika;jedinica_mjere;cijena_jedinice_mjere;" +
        "sidrena_cijena;sidrena_datum;barkod;dostupnost;poslovnica";

    private static string Cell(string? text) =>
        "\"" + CsvCellSecurity.Protect(text).Replace("\"", "\"\"") + "\"";

    public static void Write(IEnumerable<PriceItem> items,string destination)
    {
        ArgumentNullException.ThrowIfNull(items);
        ArgumentException.ThrowIfNullOrWhiteSpace(destination);
        var selection=items.ToList();
        if(selection.Count is <1 or >5000)
            throw new ArgumentException("Odaberite između 1 i 5000 stavki za pregled uvoza.");
        string full=Path.GetFullPath(destination);
        Directory.CreateDirectory(Path.GetDirectoryName(full)!);
        string temp=full+"."+Guid.NewGuid().ToString("N")+".tmp";
        try
        {
            using(var stream=new FileStream(temp,FileMode.CreateNew,FileAccess.Write,FileShare.None))
            using(var writer=new StreamWriter(stream,new UTF8Encoding(true)))
            {
                writer.WriteLine(Header);
                foreach(var item in selection)
                {
                    var kind=item.Kind switch
                    {
                        "Proizvod"=>"proizvod",
                        "Usluga"=>"usluga",
                        _=>throw new InvalidDataException("Nepoznata vrsta stavke.")
                    };
                    if(string.IsNullOrWhiteSpace(item.Name))
                        throw new InvalidDataException("Stavka nema naziv.");
                    if(item.AnchorCents.HasValue &&
                       (string.IsNullOrWhiteSpace(item.AnchorDate) ||
                        string.IsNullOrWhiteSpace(item.AnchorSource)))
                        throw new InvalidDataException("Sidrena cijena nema potvrđen datum i izvor.");
                    writer.WriteLine(string.Join(";",new[]{
                        Cell(item.Name),Cell(item.Sku),Cell(kind),Cell(""),
                        Cell(""),Cell(PriceStore.FormatEur(item.CurrentCents)),Cell(""),
                        Cell(""),Cell(item.Unit),Cell(""),
                        Cell(item.AnchorCents.HasValue ? PriceStore.FormatEur(item.AnchorCents.Value) : ""),
                        Cell(item.AnchorDate),Cell(item.Barcode),Cell(""),Cell("")
                    }));
                }
            }
            File.Move(temp,full,overwrite:true);
        }
        finally
        {
            if(File.Exists(temp))File.Delete(temp);
        }
    }
}
