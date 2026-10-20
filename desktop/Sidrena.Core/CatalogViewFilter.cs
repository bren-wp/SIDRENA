namespace Sidrena.Core;

/// <summary>
/// Fast in-memory search against a single catalogue snapshot. Every whitespace-
/// separated term must match either the name, SKU, or barcode, in any order.
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
        var terms=phrase.Split((char[]?)null,StringSplitOptions.RemoveEmptyEntries|StringSplitOptions.TrimEntries);
        return items.Where(item=>
            item.Kind==kind &&
            terms.All(term=>
                item.Name.Contains(term,StringComparison.CurrentCultureIgnoreCase) ||
                item.Sku.Contains(term,StringComparison.OrdinalIgnoreCase) ||
                item.Barcode.Contains(term,StringComparison.OrdinalIgnoreCase)) &&
            (anchorMode switch
            {
                1 => item.AnchorCents.HasValue,
                2 => !item.AnchorCents.HasValue,
                _ => true
            })).ToList();
    }
}
