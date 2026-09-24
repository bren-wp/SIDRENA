using System.Globalization;
using System.Security;
using System.Text;
using Sidrena.Windows.Models;

namespace Sidrena.Windows.Services;

public sealed class ExportService
{
    public ExportResult ExportLocalPackage(IReadOnlyList<CatalogItem> items)
    {
        var baseDir = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "SidrenaDesktop",
            "exports",
            DateTimeOffset.Now.ToString("yyyyMMdd-HHmmss", CultureInfo.InvariantCulture));

        Directory.CreateDirectory(baseDir);

        var csvPath = Path.Combine(baseDir, "sidrena-cjenik.csv");
        var xmlPath = Path.Combine(baseDir, "sidrena-cjenik.xml");
        var htmlPath = Path.Combine(baseDir, "objava-cjenika.html");
        var manifestPath = Path.Combine(baseDir, "manifest.txt");

        File.WriteAllText(csvPath, BuildCsv(items), Encoding.UTF8);
        File.WriteAllText(xmlPath, BuildXml(items), Encoding.UTF8);
        File.WriteAllText(htmlPath, BuildHtml(items), Encoding.UTF8);
        File.WriteAllText(manifestPath, BuildManifest(items), Encoding.UTF8);

        return new ExportResult
        {
            OutputPath = baseDir,
            ItemCount = items.Count,
            Files = new[] { csvPath, xmlPath, htmlPath, manifestPath }
        };
    }

    private static string BuildCsv(IReadOnlyList<CatalogItem> items)
    {
        var builder = new StringBuilder();
        builder.AppendLine("sku;name;category;current_price;anchor_price;unit;unit_price;reference_date;location;sync_status");
        foreach (var item in items)
        {
            builder.AppendLine(string.Join(';', new[]
            {
                EscapeCsv(item.Sku),
                EscapeCsv(item.Name),
                EscapeCsv(item.Category),
                item.CurrentPrice.ToString("0.00", CultureInfo.InvariantCulture),
                item.AnchorPrice.ToString("0.00", CultureInfo.InvariantCulture),
                EscapeCsv(item.Unit),
                item.UnitPrice?.ToString("0.00", CultureInfo.InvariantCulture) ?? string.Empty,
                item.ReferenceDate.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture),
                EscapeCsv(item.Location),
                EscapeCsv(item.SyncStatus)
            }));
        }

        return builder.ToString();
    }

    private static string BuildXml(IReadOnlyList<CatalogItem> items)
    {
        var builder = new StringBuilder();
        builder.AppendLine("<?xml version=\"1.0\" encoding=\"utf-8\"?>");
        builder.AppendLine("<sidrenaPricelist generatedBy=\"Sidrena Desktop\">");
        foreach (var item in items)
        {
            builder.AppendLine("  <item>");
            builder.AppendLine($"    <sku>{SecurityElement.Escape(item.Sku)}</sku>");
            builder.AppendLine($"    <name>{SecurityElement.Escape(item.Name)}</name>");
            builder.AppendLine($"    <category>{SecurityElement.Escape(item.Category)}</category>");
            builder.AppendLine($"    <currentPrice>{item.CurrentPrice.ToString("0.00", CultureInfo.InvariantCulture)}</currentPrice>");
            builder.AppendLine($"    <anchorPrice>{item.AnchorPrice.ToString("0.00", CultureInfo.InvariantCulture)}</anchorPrice>");
            builder.AppendLine($"    <unit>{SecurityElement.Escape(item.Unit)}</unit>");
            builder.AppendLine($"    <unitPrice>{item.UnitPrice?.ToString("0.00", CultureInfo.InvariantCulture) ?? string.Empty}</unitPrice>");
            builder.AppendLine($"    <referenceDate>{item.ReferenceDate.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture)}</referenceDate>");
            builder.AppendLine($"    <location>{SecurityElement.Escape(item.Location)}</location>");
            builder.AppendLine("  </item>");
        }

        builder.AppendLine("</sidrenaPricelist>");
        return builder.ToString();
    }

    private static string BuildHtml(IReadOnlyList<CatalogItem> items)
    {
        var builder = new StringBuilder();
        builder.AppendLine("<!doctype html><html lang=\"hr\"><head><meta charset=\"utf-8\"><title>Sidrena objava cjenika</title></head><body>");
        builder.AppendLine("<h1>Sidrena objava cjenika</h1><table><thead><tr><th>SKU</th><th>Naziv</th><th>Kategorija</th><th>Aktualna cijena</th><th>Sidrena cijena</th><th>Referentni datum</th><th>Lokacija</th></tr></thead><tbody>");
        foreach (var item in items)
        {
            builder.AppendLine($"<tr><td>{SecurityElement.Escape(item.Sku)}</td><td>{SecurityElement.Escape(item.Name)}</td><td>{SecurityElement.Escape(item.Category)}</td><td>{item.CurrentPrice.ToString("0.00", CultureInfo.InvariantCulture)}</td><td>{item.AnchorPrice.ToString("0.00", CultureInfo.InvariantCulture)}</td><td>{item.ReferenceDate:dd.MM.yyyy.}</td><td>{SecurityElement.Escape(item.Location)}</td></tr>");
        }

        builder.AppendLine("</tbody></table></body></html>");
        return builder.ToString();
    }

    private static string BuildManifest(IReadOnlyList<CatalogItem> items)
    {
        return $"Sidrena Desktop export\nGenerated: {DateTimeOffset.Now:O}\nItems: {items.Count}\nFiles: sidrena-cjenik.csv, sidrena-cjenik.xml, objava-cjenika.html\n";
    }

    private static string EscapeCsv(string value)
    {
        if (value.Contains(';') || value.Contains('"') || value.Contains('\n'))
        {
            return '"' + value.Replace("\"", "\"\"") + '"';
        }

        return value;
    }
}
