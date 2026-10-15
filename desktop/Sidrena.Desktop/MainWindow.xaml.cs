using System.Globalization;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using Microsoft.Win32;
using Sidrena.Core;

namespace Sidrena.Desktop;

public partial class MainWindow : Window
{
    private readonly PriceStore _store;
    private static readonly CultureInfo Hr = CultureInfo.GetCultureInfo("hr-HR");
    private sealed record DisplayItem(long Id, string Name, string Kind, string Sku, string Current, string Anchor, string AnchorDate);

    public MainWindow()
    {
        InitializeComponent();
        string location = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "Brendigo", "SIDRENA", "sidrena.db");
        _store = new PriceStore(location);
        RefreshItems();
    }

    private static decimal ParsePrice(string input)
    {
        if (!decimal.TryParse(input.Trim(), NumberStyles.Number, Hr, out var result) &&
            !decimal.TryParse(input.Trim(), NumberStyles.Number, CultureInfo.InvariantCulture, out result))
            throw new ArgumentException("Upišite valjanu cijenu, npr. 12,50.");
        PriceStore.ToCents(result);
        return result;
    }

    private void RefreshItems()
    {
        var rows = _store.ListItems();
        ItemsGrid.ItemsSource = rows.Select(p => new DisplayItem(
            p.Id, p.Name, p.Kind, p.Sku, PriceStore.FormatEur(p.CurrentCents),
            p.AnchorCents.HasValue ? PriceStore.FormatEur(p.AnchorCents.Value) : "Nije potvrđena",
            p.AnchorDate ?? "—")).ToList();
        CountLabel.Text = $"Artikli i usluge · {rows.Count}";
    }

    private void OnAddItem(object sender, RoutedEventArgs e)
    {
        try
        {
            decimal current = ParsePrice(CurrentPriceBox.Text);
            decimal? anchor = string.IsNullOrWhiteSpace(AnchorPriceBox.Text) ? null : ParsePrice(AnchorPriceBox.Text);
            string kind = (KindCombo.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "Proizvod";
            string? date = anchor.HasValue ? (AnchorDateCombo.SelectedItem as ComboBoxItem)?.Tag?.ToString() : null;
            _store.AddItem(NameBox.Text, kind, SkuBox.Text, BarcodeBox.Text, UnitBox.Text,
                current, anchor, date, anchor.HasValue ? AnchorSourceBox.Text : null);
            RefreshItems();
            NameBox.Clear();
            SkuBox.Clear();
            BarcodeBox.Clear();
            CurrentPriceBox.Clear();
            AnchorPriceBox.Clear();
            AnchorSourceBox.Clear();
            StatusLabel.Text = "Stavka je spremljena lokalno.";
            NameBox.Focus();
        }
        catch (Exception ex) when (ex is ArgumentException or InvalidOperationException or IOException or Microsoft.Data.Sqlite.SqliteException)
        {
            MessageBox.Show(this, ex.Message, "SIDRENA — provjera unosa", MessageBoxButton.OK, MessageBoxImage.Warning);
        }
    }

    private void OnUpdatePrice(object sender, RoutedEventArgs e)
    {
        if (ItemsGrid.SelectedItem is not DisplayItem selected)
        {
            MessageBox.Show(this, "Odaberite stavku u tablici.", "SIDRENA", MessageBoxButton.OK, MessageBoxImage.Information);
            return;
        }
        try
        {
            _store.UpdateCurrentPrice(selected.Id, ParsePrice(NewPriceBox.Text), ReasonBox.Text);
            RefreshItems();
            NewPriceBox.Clear();
            ReasonBox.Clear();
            StatusLabel.Text = $"Izmjena cijene stavke „{selected.Name}” trajno je zabilježena u povijesti.";
        }
        catch (Exception ex) when (ex is ArgumentException or InvalidOperationException or IOException or Microsoft.Data.Sqlite.SqliteException)
        {
            MessageBox.Show(this, ex.Message, "SIDRENA — provjera unosa", MessageBoxButton.OK, MessageBoxImage.Warning);
        }
    }

    private void OnExportCsv(object sender, RoutedEventArgs e) => Export("csv");
    private void OnExportXml(object sender, RoutedEventArgs e) => Export("xml");

    private void Export(string format)
    {
        var dialog = new SaveFileDialog
        {
            Title = "Spremi RADNI izvoz (nije propisani javni cjenik)",
            FileName = $"SIDRENA_RADNI_{DateTime.Now:yyyyMMdd}.{format}",
            Filter = format == "csv" ? "CSV (*.csv)|*.csv" : "XML (*.xml)|*.xml",
            AddExtension = true
        };
        if (dialog.ShowDialog(this) != true) return;
        try
        {
            if (format == "csv") DraftExport.Csv(_store.ListItems(), dialog.FileName);
            else DraftExport.Xml(_store.ListItems(), dialog.FileName);
            StatusLabel.Text = "Radni izvoz je spremljen. Za zakonsku objavu treba provesti validaciju službene sheme.";
        }
        catch (Exception ex) when (ex is ArgumentException or IOException or UnauthorizedAccessException)
        {
            MessageBox.Show(this, ex.Message, "SIDRENA — izvoz", MessageBoxButton.OK, MessageBoxImage.Warning);
        }
    }

    protected override void OnClosed(EventArgs e)
    {
        _store.Dispose();
        base.OnClosed(e);
    }
}
