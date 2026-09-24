using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Navigation;
using Sidrena.Windows.Services;

namespace Sidrena.Windows.Views;

public sealed partial class ImportExportPage : Page
{
    private SidrenaAppServices services = null!;

    public ImportExportPage()
    {
        InitializeComponent();
    }

    protected override void OnNavigatedTo(NavigationEventArgs e)
    {
        services = (SidrenaAppServices)e.Parameter;
        ResultText.Text = "Odaberite import ili export akciju.";
    }

    private void OnCsvImportClick(object sender, RoutedEventArgs e)
    {
        var result = services.Import.BuildCsvPreview();
        services.CatalogRepository.ReplaceAll(result.Items);
        ResultText.Text = $"CSV preview uvezao je {result.Imported} stavki. Preskočeno: {result.Skipped}.";
        FileList.ItemsSource = result.Items.Select(item => $"{item.Sku} · {item.Name}").ToArray();
    }

    private void OnXmlImportClick(object sender, RoutedEventArgs e)
    {
        var result = services.Import.BuildXmlPreview();
        services.CatalogRepository.ReplaceAll(result.Items);
        ResultText.Text = $"XML preview uvezao je {result.Imported} stavki. Preskočeno: {result.Skipped}.";
        FileList.ItemsSource = result.Items.Select(item => $"{item.Sku} · {item.Name}").ToArray();
    }

    private void OnXlsxPlanClick(object sender, RoutedEventArgs e)
    {
        ResultText.Text = services.Import.DescribeXlsxPlan();
        FileList.ItemsSource = Array.Empty<string>();
    }

    private void OnExportClick(object sender, RoutedEventArgs e)
    {
        var result = services.Export.ExportLocalPackage(services.CatalogRepository.GetAll());
        ResultText.Text = $"Exportirano {result.ItemCount} stavki u {result.OutputPath}";
        FileList.ItemsSource = result.Files;
    }
}
