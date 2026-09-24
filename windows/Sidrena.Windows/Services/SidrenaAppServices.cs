namespace Sidrena.Windows.Services;

public sealed class SidrenaAppServices
{
    private SidrenaAppServices()
    {
        CatalogRepository = new CatalogRepository();
        LegalReadiness = new LegalReadinessService();
        Export = new ExportService();
        Import = new ImportService();
        Dashboard = new DashboardService(CatalogRepository, LegalReadiness);
        WordPressSync = new WordPressSyncService();
    }

    public CatalogRepository CatalogRepository { get; }
    public LegalReadinessService LegalReadiness { get; }
    public ExportService Export { get; }
    public ImportService Import { get; }
    public DashboardService Dashboard { get; }
    public WordPressSyncService WordPressSync { get; }

    public static SidrenaAppServices Create() => new();
}
