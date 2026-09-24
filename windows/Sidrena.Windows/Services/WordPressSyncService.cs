using Sidrena.Windows.Models;

namespace Sidrena.Windows.Services;

public sealed class WordPressSyncService
{
    public WordPressConnectionSettings Settings { get; } = new();

    public string ConfigureLocalDraft(string siteUrl, string apiTokenAlias)
    {
        Settings.SiteUrl = siteUrl.Trim();
        Settings.ApiTokenAlias = apiTokenAlias.Trim();
        Settings.ManualSyncEnabled = !string.IsNullOrWhiteSpace(Settings.SiteUrl);

        return Settings.ManualSyncEnabled
            ? "WordPress veza je spremljena kao lokalni nacrt. Podaci se ne šalju dok korisnik ručno ne pokrene buduću REST sinkronizaciju."
            : "Upišite WordPress URL da bi sync nacrt bio spreman.";
    }

    public string BuildSyncPreview(IReadOnlyList<CatalogItem> items)
    {
        if (!Settings.ManualSyncEnabled)
        {
            return "REST sync nije uključen. Trenutni build radi lokalni export i priprema API sloj za kasniju ručnu sinkronizaciju.";
        }

        var ready = items.Count(item => item.SyncStatus != "Blocked" && item.CurrentPrice > 0 && item.AnchorPrice > 0);
        return $"Pripremljen sync nacrt za {ready}/{items.Count} stavki prema {Settings.SiteUrl}. Mrežni prijenos nije izveden u ovom koraku.";
    }
}
