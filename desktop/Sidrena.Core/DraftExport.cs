using System.Text;
using System.Xml.Linq;

namespace Sidrena.Core;

/// <summary>
/// Working interchange draft, NOT an official NN 101/2026 publication feed.
/// A validated, versioned statutory serializer must be used for public publication.
/// </summary>
public static class DraftExport
{
    private static string Cell(string? value)
        => "\"" + CsvCellSecurity.Protect(value).Replace("\"", "\"\"") + "\"";

    public static void Csv(IEnumerable<PriceItem> items, string destination)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(destination);
        var lines = new List<string>
        {
            "VRSTA;NAZIV;SIFRA;BARKOD;JEDINICA;AKTUALNA_EUR;SIDRENA_EUR;DATUM_SIDRENE;IZVOR_SIDRENE"
        };
        foreach (var p in items)
            lines.Add(string.Join(";", new[]
            {
                Cell(p.Kind), Cell(p.Name), Cell(p.Sku), Cell(p.Barcode), Cell(p.Unit),
                Cell(PriceStore.FormatEur(p.CurrentCents)),
                Cell(p.AnchorCents.HasValue ? PriceStore.FormatEur(p.AnchorCents.Value) : ""),
                Cell(p.AnchorDate), Cell(p.AnchorSource)
            }));
        WriteAtomically(destination, stream =>
        {
            using var writer = new StreamWriter(stream, new UTF8Encoding(true), leaveOpen: true);
            foreach (var line in lines) writer.WriteLine(line);
        });
    }

    public static void Xml(IEnumerable<PriceItem> items, string destination)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(destination);
        var doc = new XDocument(new XDeclaration("1.0", "utf-8", null),
            new XElement("SidrenaRadniIzvoz",
                new XAttribute("schema", "draft-1"),
                items.Select(p => new XElement("Stavka",
                    new XElement("Vrsta", p.Kind),
                    new XElement("Naziv", p.Name),
                    new XElement("Sifra", p.Sku),
                    new XElement("Barkod", p.Barcode),
                    new XElement("JedinicaMjere", p.Unit),
                    new XElement("AktualnaCijena", PriceStore.FormatEur(p.CurrentCents)),
                    new XElement("SidrenaCijena", p.AnchorCents.HasValue ? PriceStore.FormatEur(p.AnchorCents.Value) : ""),
                    new XElement("DatumSidrene", p.AnchorDate ?? ""),
                    new XElement("IzvorSidrene", p.AnchorSource ?? "")))));
        WriteAtomically(destination, stream => doc.Save(stream));
    }

    private static void WriteAtomically(string destination, Action<Stream> write)
    {
        string fullPath = Path.GetFullPath(destination);
        Directory.CreateDirectory(Path.GetDirectoryName(fullPath)!);
        string temporary = fullPath + "." + Guid.NewGuid().ToString("N") + ".tmp";
        try
        {
            using (var stream = new FileStream(temporary, FileMode.CreateNew, FileAccess.Write, FileShare.None))
            {
                write(stream);
                stream.Flush(true);
            }
            File.Move(temporary, fullPath, true);
        }
        finally
        {
            if (File.Exists(temporary)) File.Delete(temporary);
        }
    }
}
