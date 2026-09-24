using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Navigation;
using Sidrena.Windows.Services;
using Sidrena.Windows.ViewModels;

namespace Sidrena.Windows.Views;

public sealed partial class CatalogPage : Page
{
    private SidrenaAppServices services = null!;
    private CatalogWorkspaceViewModel viewModel = null!;

    public CatalogPage()
    {
        InitializeComponent();
    }

    protected override void OnNavigatedTo(NavigationEventArgs e)
    {
        services = (SidrenaAppServices)e.Parameter;
        viewModel = new CatalogWorkspaceViewModel(services);
        CatalogList.ItemsSource = viewModel.Items;
        CatalogStatusText.Text = $"Učitano stavki: {viewModel.Items.Count}";
    }

    private void OnRefreshClick(object sender, RoutedEventArgs e)
    {
        viewModel.Refresh();
        CatalogStatusText.Text = $"Katalog osvježen. Stavki: {viewModel.Items.Count}";
    }

    private void OnAutoFixClick(object sender, RoutedEventArgs e)
    {
        var fixedCount = services.CatalogRepository.ApplySafeDemoFixes();
        viewModel.Refresh();
        CatalogStatusText.Text = $"Auto-fix demo provjera završena. Izmjena: {fixedCount}.";
    }
}
