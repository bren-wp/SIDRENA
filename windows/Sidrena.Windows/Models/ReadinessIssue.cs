namespace Sidrena.Windows.Models;

public sealed class ReadinessIssue
{
    public string Severity { get; init; } = "Info";
    public string Area { get; init; } = string.Empty;
    public string Message { get; init; } = string.Empty;
    public string RecommendedAction { get; init; } = string.Empty;
    public string RelatedSku { get; init; } = string.Empty;

    public override string ToString()
    {
        var sku = string.IsNullOrWhiteSpace(RelatedSku) ? string.Empty : $" [{RelatedSku}]";
        return $"{Severity}{sku}: {Message}";
    }
}
