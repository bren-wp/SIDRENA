using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Navigation;
using Sidrena.Windows.Services;

namespace Sidrena.Windows.Views;

public sealed partial class WordPressSyncPage : Page
{
    private SidrenaAppServices services = null!;

    public WordPressSyncPage()
    {
        InitializeComponent();
    }

    protected override void OnNavigatedTo(NavigationEventArgs e)
    {
        services = (SidrenaAppServices)e.Parameter;
        SiteUrlBox.Text = services.WordPressSync.Settings.SiteUrl;
        TokenAliasBox.Text = services.WordPressSync.Settings.ApiTokenAlias;
        SyncResultText.Text = "REST sync je pripremljen kao stub/apstrakcija. Nema mrežnog slanja u ovom koraku.";
    }

    private void OnSaveDraftClick(object sender, RoutedEventArgs e)
    {
        SyncResultText.Text = services.WordPressSync.ConfigureLocalDraft(SiteUrlBox.Text, TokenAliasBox.Text);
    }

    private void OnPreviewSyncClick(object sender, RoutedEventArgs e)
    {
        SyncResultText.Text = services.WordPressSync.BuildSyncPreview(services.CatalogRepository.GetAll());
    }
}
