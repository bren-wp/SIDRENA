using System.Globalization;

namespace Sidrena.Core;

/// <summary>Accept one decimal separator, reject thousand separators and excess precision.</summary>
public static class MoneyParser
{
    public static decimal Parse(string? input)
    {
        if (string.IsNullOrWhiteSpace(input))
            throw new ArgumentException("Unesite cijenu, npr. 12,50 ili 12.50.", nameof(input));
        string text = input.Trim();
        if (text.Contains(',') && text.Contains('.'))
            throw new ArgumentException("Ne miješajte decimalne separatore.", nameof(input));
        int separator = text.IndexOfAny(new[] { ',', '.' });
        if (separator >= 0 && (separator == 0 || separator == text.Length - 1 || text.Length - separator - 1 > 2))
            throw new ArgumentException("Cijena smije imati najviše dvije decimale.", nameof(input));
        CultureInfo culture = text.Contains(',') ? CultureInfo.GetCultureInfo("hr-HR") : CultureInfo.InvariantCulture;
        if (!decimal.TryParse(text, NumberStyles.AllowDecimalPoint, culture, out var price))
            throw new ArgumentException("Cijena mora biti broj bez razdjelnika tisućica.", nameof(input));
        PriceStore.ToCents(price);
        return price;
    }
}
