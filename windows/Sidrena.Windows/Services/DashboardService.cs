using Sidrena.Windows.Models;

namespace Sidrena.Windows.Services;

public sealed class DashboardService
{
    private readonly CatalogRepository catalogRepository;
    private readonly LegalReadinessService legalReadiness;

    public DashboardService(CatalogRepository catalogRepository, LegalReadinessService legalReadiness)
    {
        this.catalogRepository = catalogRepository;
        this.legalReadiness = legalReadiness;
    }

    public DashboardSnapshot CreateSnapshot()
    {
        var items = catalogRepository.GetAll();
        var issues = legalReadiness.Validate(items);
        var blockingIssues = issues.Count(issue => issue.Severity is "Error" or "Warning");
        var lastPublication = items
            .Where(item => item.LastPublishedAt is not null)
            .Select(item => item.LastPublishedAt!.Value)
            .DefaultIfEmpty()
            .Max();

        return new DashboardSnapshot
        {
            CatalogStatus = blockingIssues == 0 ? "Spreman za export" : "Potrebna provjera",
            ProductCount = items.Count,
            ReadyCount = items.Count(item => item.CurrentPrice > 0 && item.AnchorPrice > 0 && !string.IsNullOrWhiteSpace(item.Location)),
            IssueCount = blockingIssues,
            LastPublicationLabel = lastPublication == default ? "nije izrađena" : lastPublication.ToLocalTime().ToString("dd.MM.yyyy. HH:mm"),
            SyncStatus = items.Any(item => item.SyncStatus == "Blocked") ? "Lokalno · blokirano dok postoje greške" : "Lokalno · spremno",
            LegalReadinessStatus = blockingIssues == 0 ? "Tehnička provjera uredna" : "Nisu ispunjena sva tehnička polja"
        };
    }
}
