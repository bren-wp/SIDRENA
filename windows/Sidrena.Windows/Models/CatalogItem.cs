namespace Sidrena.Windows.Models;

public sealed class CatalogItem
{
    public string Sku { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string Category { get; set; } = string.Empty;
    public decimal CurrentPrice { get; set; }
    public decimal AnchorPrice { get; set; }
    public string Unit { get; set; } = string.Empty;
    public decimal? UnitPrice { get; set; }
    public DateOnly ReferenceDate { get; set; }
    public string Location { get; set; } = string.Empty;
    public string ErrorStatus { get; set; } = "OK";
    public string SyncStatus { get; set; } = "Local";
    public DateTimeOffset? LastPublishedAt { get; set; }
}
