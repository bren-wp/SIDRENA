namespace Sidrena.Core;

/// <summary>
/// In-memory view filter for the local catalogue. No repeated SQLite reads
/// on every keystroke; callers refresh their snapshot after each mutation.
/// </summary>
public static class CatalogViewFilter
{
    public static IReadOnlyList<PriceItem> Apply(
        IEnumerable<PriceItem> items,string kind,string? query,int anchorMode)
    {
        ArgumentNullException.ThrowIfNull(items);
        if(kind is not ("Proizvod" or "Usluga"))
            throw new ArgumentException("Nevažeća vrsta kataloga.",nameof(kind));
        if(anchorMode is <0 or >2)
            throw new ArgumentOutOfRangeException(nameof(anchorMode));
        var phrase=(query??string.Empty).Trim();
        if(phrase.Length>256)
            throw new ArgumentException("Pretraživanje mora imati najviše 256 znakova.",nameof(query));
        return items.Where(item=>
            item.Kind==kind &&
            (phrase.Length==0 ||
             item.Name.Contains(phrase,StringComparison.CurrentCultureIgnoreCase) ||
             item.Sku.Contains(phrase,StringComparison.OrdinalIgnoreCase) ||
             item.Barcode.Contains(phrase,StringComparison.OrdinalIgnoreCase)) &&
            (anchorMode switch
            {
                1 => item.AnchorCents.HasValue,
                2 => !item.AnchorCents.HasValue,
                _ => true
            })).ToList();
    }
}
