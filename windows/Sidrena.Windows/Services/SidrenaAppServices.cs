using System.Globalization;
using System.Security;
using System.Text;

namespace Sidrena.Windows.Services;

public sealed class SidrenaCatalogItem
{
    public string Sku { get; init; } = string.Empty;
    public string Name { get; init; } = string.Empty;
    public decimal CurrentPrice { get; init; }
    public decimal AnchorPrice { get; init; }
    public string Unit { get; init; } = string.Empty;
    public decimal? UnitPrice { get; init; }
    public DateOnly ReferenceDate { get; init; }
    public string Location { get; init; } = string.Empty;
}

public sealed class ReadinessIssue
{
    public string Severity { get; init; } = "Info";
    public string Message { get; init; } = string.Empty;

    public override string ToString() => $"{Severity}: {Message}";
}

public static class SampleCatalogService
{
    public static IReadOnlyList<SidrenaCatalogItem> LoadDemoCatalog()
    {
        return new[]
        {
            new SidrenaCatalogItem
            {
                Sku = "SID-001",
                Name = "Osnovna usluga dostave",
                CurrentPrice = 4.99m,
                AnchorPrice = 5.49m,
                Unit = "usluga",
                UnitPrice = 4.99m,
                ReferenceDate = LegalReadinessService.StandardReferenceDate,
                Location = "Webshop"
            },
            new SidrenaCatalogItem
            {
                Sku = "SID-002",
                Name = "Demo FMCG proizvod",
                CurrentPrice = 2.39m,
                AnchorPrice = 2.69m,
                Unit = "kom",
                UnitPrice = 2.39m,
                ReferenceDate = LegalReadinessService.FmcgReferenceDate,
                Location = "Poslovnica Rijeka"
            },
            new SidrenaCatalogItem
            {
                Sku = "SID-003",
                Name = "Proizvod s upozorenjem",
                CurrentPrice = 0m,
                AnchorPrice = 0m,
                Unit = string.Empty,
                UnitPrice = null,
                ReferenceDate = new DateOnly(2026, 1, 1),
                Location = string.Empty
            }
        };
    }
}

public static class LegalReadinessService
{
    public static readonly DateOnly StandardReferenceDate = new(2026, 9, 10);
    public static readonly DateOnly FmcgReferenceDate = new(2025, 5, 2);
    public static readonly DateOnly EffectiveDate = new(2026, 10, 1);

    public static IReadOnlyList<ReadinessIssue> Validate(IReadOnlyList<SidrenaCatalogItem> items)
    {
        var issues = new List<ReadinessIssue>();

        if (items.Count == 0)
        {
            issues.Add(new ReadinessIssue { Severity = "Error", Message = "Katalog je prazan." });
            return issues;
        }

        var duplicateSkus = items
            .Where(item => !string.IsNullOrWhiteSpace(item.Sku))
            .GroupBy(item => item.Sku.Trim(), StringComparer.OrdinalIgnoreCase)
            .Where(group => group.Count() > 1)
            .Select(group => group.Key)
            .ToArray();

        foreach (var sku in duplicateSkus)
        {
            issues.Add(new ReadinessIssue { Severity = "Warning", Message = $"Dupli SKU: {sku}." });
        }

        foreach (var item in items)
        {
            if (string.IsNullOrWhiteSpace(item.Sku))
            {
                issues.Add(new ReadinessIssue { Severity = "Error", Message = $"{item.Name}: nedostaje SKU." });
            }

            if (string.IsNullOrWhiteSpace(item.Name))
            {
                issues.Add(new ReadinessIssue { Severity = "Error", Message = $"{item.Sku}: nedostaje naziv." });
            }

            if (item.CurrentPrice <= 0)
            {
                issues.Add(new ReadinessIssue { Severity = "Error", Message = $"{item.Sku}: aktualna cijena mora biti veća od 0." });
            }

            if (item.AnchorPrice <= 0)
            {
                issues.Add(new ReadinessIssue { Severity = "Error", Message = $"{item.Sku}: sidrena cijena mora biti veća od 0." });
            }

            if (item.UnitPrice is null || item.UnitPrice <= 0)
            {
                issues.Add(new ReadinessIssue { Severity = "Warning", Message = $"{item.Sku}: nedostaje jedinična cijena." });
            }

            if (string.IsNullOrWhiteSpace(item.Unit))
            {
                issues.Add(new ReadinessIssue { Severity = "Warning", Message = $"{item.Sku}: nedostaje jedinica mjere." });
            }

            if (string.IsNullOrWhiteSpace(item.Location))
            {
                issues.Add(new ReadinessIssue { Severity = "Warning", Message = $"{item.Sku}: lokacija ili webshop nisu definirani." });
            }

            if (item.ReferenceDate != StandardReferenceDate && item.ReferenceDate != FmcgReferenceDate)
            {
                issues.Add(new ReadinessIssue
                {
                    Severity = "Error",
                    Message = $"{item.Sku}: referentni datum mora biti {StandardReferenceDate:dd.MM.yyyy.} ili {FmcgReferenceDate:dd.MM.yyyy.}."
                });
            }
        }

        if (!issues.Any(issue => issue.Severity == "Error"))
        {
            issues.Add(new ReadinessIssue { Severity = "Success", Message = "Katalog je tehnički spreman za pripremu objave." });
        }

        return issues;
    }
}

public static class ExportService
{
    public static string ExportLocalPackage(IReadOnlyList<SidrenaCatalogItem> items)
    {
        var baseDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "SidrenaDesktop", "exports", DateTimeOffset.Now.ToString("yyyyMMdd-HHmmss", CultureInfo.InvariantCulture));
        Directory.CreateDirectory(baseDir);

        File.WriteAllText(Path.Combine(baseDir, "sidrena-cjenik.csv"), BuildCsv(items), Encoding.UTF8);
        File.WriteAllText(Path.Combine(baseDir, "sidrena-cjenik.xml"), BuildXml(items), Encoding.UTF8);
        File.WriteAllText(Path.Combine(baseDir, "objava-cjenika.html"), BuildHtml(items), Encoding.UTF8);
        File.WriteAllText(Path.Combine(baseDir, "manifest.txt"), $"Sidrena Desktop export\nGenerated: {DateTimeOffset.Now:O}\nItems: {items.Count}\n", Encoding.UTF8);

        return baseDir;
    }

    private static string BuildCsv(IReadOnlyList<SidrenaCatalogItem> items)
    {
        var builder = new StringBuilder();
        builder.AppendLine("sku;name;current_price;anchor_price;unit;unit_price;reference_date;location");
        foreach (var item in items)
        {
            builder.AppendLine(string.Join(';', new[]
            {
                EscapeCsv(item.Sku),
                EscapeCsv(item.Name),
                item.CurrentPrice.ToString("0.00", CultureInfo.InvariantCulture),
                item.AnchorPrice.ToString("0.00", CultureInfo.InvariantCulture),
                EscapeCsv(item.Unit),
                item.UnitPrice?.ToString("0.00", CultureInfo.InvariantCulture) ?? string.Empty,
                item.ReferenceDate.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture),
                EscapeCsv(item.Location)
            }));
        }

        return builder.ToString();
    }

    private static string BuildXml(IReadOnlyList<SidrenaCatalogItem> items)
    {
        var builder = new StringBuilder();
        builder.AppendLine("<?xml version=\"1.0\" encoding=\"utf-8\"?>");
        builder.AppendLine("<sidrenaPricelist>");
        foreach (var item in items)
        {
            builder.AppendLine("  <item>");
            builder.AppendLine($"    <sku>{SecurityElement.Escape(item.Sku)}</sku>");
            builder.AppendLine($"    <name>{SecurityElement.Escape(item.Name)}</name>");
            builder.AppendLine($"    <currentPrice>{item.CurrentPrice.ToString("0.00", CultureInfo.InvariantCulture)}</currentPrice>");
            builder.AppendLine($"    <anchorPrice>{item.AnchorPrice.ToString("0.00", CultureInfo.InvariantCulture)}</anchorPrice>");
            builder.AppendLine($"    <unit>{SecurityElement.Escape(item.Unit)}</unit>");
            builder.AppendLine($"    <unitPrice>{(item.UnitPrice?.ToString("0.00", CultureInfo.InvariantCulture) ?? string.Empty)}</unitPrice>");
            builder.AppendLine($"    <referenceDate>{item.ReferenceDate.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture)}</referenceDate>");
            builder.AppendLine($"    <location>{SecurityElement.Escape(item.Location)}</location>");
            builder.AppendLine("  </item>");
        }
        builder.AppendLine("</sidrenaPricelist>");
        return builder.ToString();
    }

    private static string BuildHtml(IReadOnlyList<SidrenaCatalogItem> items)
    {
        var builder = new StringBuilder();
        builder.AppendLine("<!doctype html><html lang=\"hr\"><meta charset=\"utf-8\"><title>Sidrena objava cjenika</title>");
        builder.AppendLine("<body><h1>Sidrena objava cjenika</h1><table><thead><tr><th>SKU</th><th>Naziv</th><th>Aktualna cijena</th><th>Sidrena cijena</th><th>Referentni datum</th><th>Lokacija</th></tr></thead><tbody>");
        foreach (var item in items)
        {
            builder.AppendLine($"<tr><td>{SecurityElement.Escape(item.Sku)}</td><td>{SecurityElement.Escape(item.Name)}</td><td>{item.CurrentPrice:0.00}</td><td>{item.AnchorPrice:0.00}</td><td>{item.ReferenceDate:dd.MM.yyyy.}</td><td>{SecurityElement.Escape(item.Location)}</td></tr>");
        }
        builder.AppendLine("</tbody></table></body></html>");
        return builder.ToString();
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
