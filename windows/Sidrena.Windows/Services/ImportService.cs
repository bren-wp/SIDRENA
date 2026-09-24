using Sidrena.Windows.Models;

namespace Sidrena.Windows.Services;

public sealed class ImportService
{
    public ImportResult BuildCsvPreview()
    {
        return new ImportResult
        {
            SourceFormat = "CSV",
            Imported = 3,
            Skipped = 0,
            Items = BuildImportedItems("CSV")
        };
    }

    public ImportResult BuildXmlPreview()
    {
        return new ImportResult
        {
            SourceFormat = "XML",
            Imported = 3,
            Skipped = 0,
            Items = BuildImportedItems("XML")
        };
    }

    public string DescribeXlsxPlan()
    {
        return "XLSX import je planiran kao zaseban parser sloj; trenutni build namjerno ne dodaje lažnu XLSX podršku.";
    }

    private static IReadOnlyList<CatalogItem> BuildImportedItems(string source)
    {
        return new[]
        {
            new CatalogItem
            {
                Sku = $"{source}-001",
                Name = "Uvezena usluga savjetovanja",
                Category = "Usluge",
                CurrentPrice = 29.90m,
                AnchorPrice = 39.90m,
                Unit = "sat",
                UnitPrice = 29.90m,
                ReferenceDate = LegalReadinessService.StandardReferenceDate,
                Location = "Webshop",
                ErrorStatus = "OK",
                SyncStatus = "Ready"
            },
            new CatalogItem
            {
                Sku = $"{source}-002",
                Name = "Uvezeni proizvod A",
                Category = "Maloprodaja",
                CurrentPrice = 9.99m,
                AnchorPrice = 12.49m,
                Unit = "kom",
                UnitPrice = 9.99m,
                ReferenceDate = LegalReadinessService.StandardReferenceDate,
                Location = "Poslovnica",
                ErrorStatus = "OK",
                SyncStatus = "Ready"
            },
            new CatalogItem
            {
                Sku = $"{source}-003",
                Name = "Uvezeni FMCG proizvod",
                Category = "FMCG",
                CurrentPrice = 1.49m,
                AnchorPrice = 1.79m,
                Unit = "kom",
                UnitPrice = 1.49m,
                ReferenceDate = LegalReadinessService.FmcgReferenceDate,
                Location = "Webshop",
                ErrorStatus = "OK",
                SyncStatus = "Ready"
            }
        };
    }
}
