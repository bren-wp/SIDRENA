using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Media;
using Sidrena.Windows.Services;
using Sidrena.Windows.Views;

namespace Sidrena.Windows;

public sealed partial class MainWindow : Window
{
    private readonly SidrenaAppServices services;

    public MainWindow()
    {
        InitializeComponent();
        ExtendsContentIntoTitleBar = true;
        SetTitleBar(AppTitleBar);
        SystemBackdrop = new MicaBackdrop();

        services = SidrenaAppServices.Create();
        ContentFrame.Navigate(typeof(DashboardPage), services);
    }

    private void OnNavigationSelectionChanged(NavigationView sender, NavigationViewSelectionChangedEventArgs args)
    {
        if (args.SelectedItem is not NavigationViewItem item || item.Tag is not string tag)
        {
            return;
        }

        sender.Header = item.Content?.ToString() ?? "Sidrena";
        var targetPage = tag switch
        {
            "catalog" => typeof(CatalogPage),
            "importExport" => typeof(ImportExportPage),
            "legal" => typeof(LegalReadinessPage),
            "sync" => typeof(WordPressSyncPage),
            _ => typeof(DashboardPage)
        };

        if (ContentFrame.CurrentSourcePageType != targetPage)
        {
            ContentFrame.Navigate(targetPage, services);
        }
    }
}
