namespace Sidrena.Windows.Models;

public sealed class DashboardSnapshot
{
    public string CatalogStatus { get; init; } = string.Empty;
    public int ProductCount { get; init; }
    public int ReadyCount { get; init; }
    public int IssueCount { get; init; }
    public string LastPublicationLabel { get; init; } = string.Empty;
    public string SyncStatus { get; init; } = string.Empty;
    public string LegalReadinessStatus { get; init; } = string.Empty;
}
