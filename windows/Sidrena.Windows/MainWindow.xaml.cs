using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Media;
using Sidrena.Windows.Services;

namespace Sidrena.Windows;

public sealed partial class MainWindow : Window
{
    private readonly IReadOnlyList<SidrenaCatalogItem> catalog;

    public MainWindow()
    {
        InitializeComponent();
        ExtendsContentIntoTitleBar = true;
        SetTitleBar(AppTitleBar);
        SystemBackdrop = new MicaBackdrop();

        catalog = SampleCatalogService.LoadDemoCatalog();
        RefreshDashboard();
    }

    private void RefreshDashboard()
    {
        var issues = LegalReadinessService.Validate(catalog);
        var errorCount = issues.Count(issue => issue.Severity is "Error" or "Warning");

        ProductCountText.Text = catalog.Count.ToString();
        IssueCountText.Text = errorCount.ToString();
        ReadyCountText.Text = catalog.Count(item => item.CurrentPrice > 0 && item.AnchorPrice > 0).ToString();
        IssueList.ItemsSource = issues.Select(issue => issue.ToString()).ToArray();
    }

    private void OnValidateClick(object sender, RoutedEventArgs e)
    {
        RefreshDashboard();
    }

    private void OnExportClick(object sender, RoutedEventArgs e)
    {
        var exportPath = ExportService.ExportLocalPackage(catalog);
        LastExportText.Text = exportPath;
    }
}
