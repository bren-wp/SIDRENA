using Sidrena.Windows.Models;

namespace Sidrena.Windows.Services;

public sealed class LegalReadinessService
{
    public static readonly DateOnly StandardReferenceDate = new(2026, 9, 10);
    public static readonly DateOnly FmcgReferenceDate = new(2025, 5, 2);
    public static readonly DateOnly EffectiveDate = new(2026, 10, 1);

    public IReadOnlyList<string> SourceNotes { get; } = new[]
    {
        "NN 101/2026",
        "NN 105/2026",
        "Službena pojašnjenja Ministarstva gospodarstva od 22.09.2026.",
        "Aplikacija tehnički pomaže u provjeri podataka, ali ne daje automatsku pravnu potvrdu."
    };

    public IReadOnlyList<ReadinessIssue> Validate(IReadOnlyList<CatalogItem> items)
    {
        var issues = new List<ReadinessIssue>();

        if (items.Count == 0)
        {
            issues.Add(new ReadinessIssue
            {
                Severity = "Error",
                Area = "Katalog",
                Message = "Katalog je prazan.",
                RecommendedAction = "Uvezite CSV/XML katalog ili dodajte proizvode prije exporta."
            });
            return issues;
        }

        foreach (var duplicate in FindDuplicateSkus(items))
        {
            issues.Add(new ReadinessIssue
            {
                Severity = "Warning",
                Area = "Katalog",
                RelatedSku = duplicate,
                Message = "SKU se pojavljuje više puta.",
                RecommendedAction = "Provjerite radi li se o varijacijama ili grešci u katalogu."
            });
        }

        foreach (var item in items)
        {
            ValidateItem(item, issues);
        }

        if (!issues.Any(issue => issue.Severity == "Error"))
        {
            issues.Add(new ReadinessIssue
            {
                Severity = "Success",
                Area = "Legal readiness",
                Message = "Katalog je tehnički spreman za lokalni export.",
                RecommendedAction = "Pregledajte poslovne podatke prije javne objave."
            });
        }

        return issues;
    }

    private static IEnumerable<string> FindDuplicateSkus(IReadOnlyList<CatalogItem> items)
    {
        return items
            .Where(item => !string.IsNullOrWhiteSpace(item.Sku))
            .GroupBy(item => item.Sku.Trim(), StringComparer.OrdinalIgnoreCase)
            .Where(group => group.Count() > 1)
            .Select(group => group.Key);
    }

    private static void ValidateItem(CatalogItem item, List<ReadinessIssue> issues)
    {
        AddIf(issues, string.IsNullOrWhiteSpace(item.Sku), "Error", "Identitet", item.Sku, item.Name, "Nedostaje SKU/šifra.", "Dodajte jedinstvenu šifru proizvoda ili usluge.");
        AddIf(issues, string.IsNullOrWhiteSpace(item.Name), "Error", "Identitet", item.Sku, item.Name, "Nedostaje naziv.", "Upišite jasan naziv za javni cjenik.");
        AddIf(issues, item.CurrentPrice <= 0, "Error", "Cijene", item.Sku, item.Name, "Aktualna cijena mora biti veća od 0.", "Unesite aktualnu prodajnu cijenu.");
        AddIf(issues, item.AnchorPrice <= 0, "Error", "Cijene", item.Sku, item.Name, "Sidrena cijena mora biti veća od 0.", "Unesite referentnu/sidrenu cijenu iz poslovne evidencije.");
        AddIf(issues, item.UnitPrice is null || item.UnitPrice <= 0, "Warning", "Jedinična cijena", item.Sku, item.Name, "Nedostaje jedinična cijena.", "Unesite jediničnu cijenu kada je primjenjiva.");
        AddIf(issues, string.IsNullOrWhiteSpace(item.Unit), "Warning", "Jedinična cijena", item.Sku, item.Name, "Nedostaje jedinica mjere.", "Odredite mjeru, npr. kom, kg, l, m ili usluga.");
        AddIf(issues, string.IsNullOrWhiteSpace(item.Location), "Warning", "Lokacija", item.Sku, item.Name, "Lokacija ili webshop nisu definirani.", "Odaberite poslovnicu ili webshop kanal.");
        AddIf(issues, item.ReferenceDate != StandardReferenceDate && item.ReferenceDate != FmcgReferenceDate, "Error", "Referentni datum", item.Sku, item.Name, $"Referentni datum mora biti {StandardReferenceDate:dd.MM.yyyy.} ili {FmcgReferenceDate:dd.MM.yyyy.}.", "Odaberite ispravan referentni datum prema kategoriji.");
    }

    private static void AddIf(List<ReadinessIssue> issues, bool condition, string severity, string area, string sku, string name, string message, string action)
    {
        if (!condition)
        {
            return;
        }

        issues.Add(new ReadinessIssue
        {
            Severity = severity,
            Area = area,
            RelatedSku = string.IsNullOrWhiteSpace(sku) ? name : sku,
            Message = message,
            RecommendedAction = action
        });
    }
}
