using System.Collections.ObjectModel;
using Sidrena.Windows.Models;
using Sidrena.Windows.Services;

namespace Sidrena.Windows.ViewModels;

public sealed class CatalogWorkspaceViewModel
{
    private readonly SidrenaAppServices services;

    public CatalogWorkspaceViewModel(SidrenaAppServices services)
    {
        this.services = services;
        Refresh();
    }

    public ObservableCollection<CatalogItem> Items { get; } = new();

    public void Refresh()
    {
        Items.Clear();
        foreach (var item in services.CatalogRepository.GetAll())
        {
            Items.Add(item);
        }
    }
}
