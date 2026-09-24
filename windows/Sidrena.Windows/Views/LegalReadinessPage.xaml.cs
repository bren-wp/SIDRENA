using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Navigation;
using Sidrena.Windows.Services;

namespace Sidrena.Windows.Views;

public sealed partial class LegalReadinessPage : Page
{
    private SidrenaAppServices services = null!;

    public LegalReadinessPage()
    {
        InitializeComponent();
    }

    protected override void OnNavigatedTo(NavigationEventArgs e)
    {
        services = (SidrenaAppServices)e.Parameter;
        SourceNotesList.ItemsSource = services.LegalReadiness.SourceNotes;
        ValidateCatalog();
    }

    private void OnValidateClick(object sender, RoutedEventArgs e)
    {
        ValidateCatalog();
    }

    private void ValidateCatalog()
    {
        var issues = services.LegalReadiness.Validate(services.CatalogRepository.GetAll());
        IssueList.ItemsSource = issues.Select(issue => $"{issue.Area} · {issue}").ToArray();
        StatusText.Text = $"Provjera završena. Stavki nalaza: {issues.Count}.";
    }
}
