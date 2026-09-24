using Sidrena.Windows.Models;

namespace Sidrena.Windows.Services;

public sealed class CatalogRepository
{
    private readonly List<CatalogItem> items = new();

    public CatalogRepository()
    {
        ResetToPremiumDemoCatalog();
    }

    public IReadOnlyList<CatalogItem> GetAll() => items.ToArray();

    public void ReplaceAll(IEnumerable<CatalogItem> importedItems)
    {
        items.Clear();
        items.AddRange(importedItems);
    }

    public void ResetToPremiumDemoCatalog()
    {
        items.Clear();
        items.AddRange(new[]
        {
            new CatalogItem
            {
                Sku = "SID-001",
                Name = "Osnovna usluga dostave",
                Category = "Usluge",
                CurrentPrice = 4.99m,
                AnchorPrice = 5.49m,
                Unit = "usluga",
                UnitPrice = 4.99m,
                ReferenceDate = LegalReadinessService.StandardReferenceDate,
                Location = "Webshop",
                ErrorStatus = "OK",
                SyncStatus = "Ready",
                LastPublishedAt = DateTimeOffset.Now.AddDays(-1)
            },
            new CatalogItem
            {
                Sku = "SID-002",
                Name = "Demo FMCG proizvod",
                Category = "FMCG",
                CurrentPrice = 2.39m,
                AnchorPrice = 2.69m,
                Unit = "kom",
                UnitPrice = 2.39m,
                ReferenceDate = LegalReadinessService.FmcgReferenceDate,
                Location = "Poslovnica Rijeka",
                ErrorStatus = "OK",
                SyncStatus = "Ready",
                LastPublishedAt = DateTimeOffset.Now.AddDays(-1)
            },
            new CatalogItem
            {
                Sku = "SID-003",
                Name = "Proizvod s upozorenjem",
                Category = "Provjera",
                CurrentPrice = 0m,
                AnchorPrice = 0m,
                Unit = string.Empty,
                UnitPrice = null,
                ReferenceDate = new DateOnly(2026, 1, 1),
                Location = string.Empty,
                ErrorStatus = "Needs review",
                SyncStatus = "Blocked"
            }
        });
    }

    public int ApplySafeDemoFixes()
    {
        var fixedCount = 0;
        foreach (var item in items)
        {
            if (item.CurrentPrice <= 0)
            {
                item.CurrentPrice = 1.00m;
                fixedCount++;
            }

            if (item.AnchorPrice <= 0)
            {
                item.AnchorPrice = item.CurrentPrice;
                fixedCount++;
            }

            if (string.IsNullOrWhiteSpace(item.Unit))
            {
                item.Unit = "kom";
                fixedCount++;
            }

            if (item.UnitPrice is null || item.UnitPrice <= 0)
            {
                item.UnitPrice = item.CurrentPrice;
                fixedCount++;
            }

            if (string.IsNullOrWhiteSpace(item.Location))
            {
                item.Location = "Webshop";
                fixedCount++;
            }

            if (item.ReferenceDate != LegalReadinessService.StandardReferenceDate && item.ReferenceDate != LegalReadinessService.FmcgReferenceDate)
            {
                item.ReferenceDate = LegalReadinessService.StandardReferenceDate;
                fixedCount++;
            }
        }

        return fixedCount;
    }
}
