using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Navigation;
using Sidrena.Windows.Services;

namespace Sidrena.Windows.Views;

public sealed partial class DashboardPage : Page
{
    private SidrenaAppServices services = null!;

    public DashboardPage()
    {
        InitializeComponent();
    }

    protected override void OnNavigatedTo(NavigationEventArgs e)
    {
        services = (SidrenaAppServices)e.Parameter;
        RefreshDashboard();
    }

    private void RefreshDashboard()
    {
        var snapshot = services.Dashboard.CreateSnapshot();
        var issues = services.LegalReadiness.Validate(services.CatalogRepository.GetAll());

        CatalogStatusText.Text = snapshot.CatalogStatus;
        ProductCountText.Text = snapshot.ProductCount.ToString();
        ReadyCountText.Text = snapshot.ReadyCount.ToString();
        IssueCountText.Text = snapshot.IssueCount.ToString();
        LastPublicationText.Text = snapshot.LastPublicationLabel;
        SyncStatusText.Text = snapshot.SyncStatus;
        LegalStatusText.Text = snapshot.LegalReadinessStatus;
        IssueList.ItemsSource = issues.Select(issue => issue.ToString()).ToArray();
    }

    private void OnValidateClick(object sender, RoutedEventArgs e)
    {
        RefreshDashboard();
        ActionStatusText.Text = "Provjera kataloga je završena lokalno.";
    }

    private void OnExportClick(object sender, RoutedEventArgs e)
    {
        var result = services.Export.ExportLocalPackage(services.CatalogRepository.GetAll());
        ActionStatusText.Text = $"Exportirano {result.ItemCount} stavki u: {result.OutputPath}";
        RefreshDashboard();
    }

    private void OnLoadDemoClick(object sender, RoutedEventArgs e)
    {
        services.CatalogRepository.ResetToPremiumDemoCatalog();
        ActionStatusText.Text = "Demo katalog je ponovno učitan.";
        RefreshDashboard();
    }
}
