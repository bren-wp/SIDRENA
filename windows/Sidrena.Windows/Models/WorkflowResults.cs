namespace Sidrena.Windows.Models;

public sealed class ExportResult
{
    public string OutputPath { get; init; } = string.Empty;
    public int ItemCount { get; init; }
    public string[] Files { get; init; } = Array.Empty<string>();
}

public sealed class ImportResult
{
    public string SourceFormat { get; init; } = string.Empty;
    public int Imported { get; init; }
    public int Skipped { get; init; }
    public IReadOnlyList<CatalogItem> Items { get; init; } = Array.Empty<CatalogItem>();
}

public sealed class WordPressConnectionSettings
{
    public string SiteUrl { get; set; } = string.Empty;
    public string ApiTokenAlias { get; set; } = string.Empty;
    public bool ManualSyncEnabled { get; set; }
}
