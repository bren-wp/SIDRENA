using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Win32;
using Sidrena.Core;
using U=Sidrena.Desktop.UiKit;

namespace Sidrena.Desktop;

public partial class MainWindow : Window
{
    private readonly PriceStore _prices;
    private readonly WorkspaceStore _workspace;
    private readonly string _databasePath;
    private string _activePage = "Pregled";
    private const string DraftWarning = "RADNI IZVOZ — nije propisani javni cjenik. Prije objave potrebna je validacija konačnih pravila.";
    private static readonly CultureInfo Hr = CultureInfo.GetCultureInfo("hr-HR");
    private sealed record ItemRow(long Id,string Name,string Kind,string Sku,string Barcode,string Unit,string Current,string Anchor,string AnchorDate);
    private sealed record AuditRow(long Id,string Name,string Kind,string Previous,string Current,string Change,string Reason,string Time);
    private sealed record LocationRow(long Id,string Name,string Address,string City,string Status);
    private sealed record ArchiveRow(long Id,string Name,string Format,string Count,string Date,string Status,string Path);

    public MainWindow()
    {
        InitializeComponent();
        ConfigureNavigationIcons();
        _databasePath=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "Brendigo","SIDRENA","sidrena.db");
        _prices=new PriceStore(_databasePath);
        _workspace=new WorkspaceStore(_databasePath);
        Navigate("Pregled");
        if(Environment.GetEnvironmentVariable("SIDRENA_UI_SMOKE")=="1")
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                string marker=Path.Combine(Path.GetDirectoryName(_databasePath)!, "ui-smoke.ok");
                try
                {
                    foreach(var page in new[]{"Pregled","Artikli","Usluge","Povijest cijena","Cjenici",
                        "Lokacije","Sinkronizacija","Arhiva","Postavke","Pomoć"})
                    {
                        Navigate(page);
                        PageHost.UpdateLayout();
                    }
                    File.WriteAllText(marker,"SIDRENA UI screens: 10/10 passed.");
                    Application.Current.Shutdown(0);
                }
                catch(Exception ex)
                {
                    File.WriteAllText(marker,"UI smoke failure: "+ex);
                    Application.Current.Shutdown(1);
                }
            }),System.Windows.Threading.DispatcherPriority.ApplicationIdle);
        }
    }

    private void OnNavigate(object sender,RoutedEventArgs e)
    {
        if(sender is Button b && b.Tag is string page) Navigate(page);
    }
    private void OnWindowKeyDown(object sender,KeyEventArgs e)
    {
        if((Keyboard.Modifiers&ModifierKeys.Control)!=0 && e.Key==Key.N){ Navigate("Artikli");e.Handled=true; }
        else if(e.Key==Key.F1){Navigate("Pomoć");e.Handled=true;}
    }

    private void Navigate(string page)
    {
        _activePage=page;
        PageTitle.Text=page=="Pregled"?"Sidrene cijene i digitalni cjenici":page;
        PageSubtitle.Text=page switch{
            "Pregled"=>"Upravljajte artiklima, cijenama i radnim cjenicima na jednom mjestu.",
            "Artikli"=>"Upravljanje proizvodima, cijenama i osnovnim podacima.",
            "Usluge"=>"Pregled usluga, njihove cijene i povijest promjena.",
            "Povijest cijena"=>"Sve zabilježene izmjene cijena artikala i usluga kroz vrijeme.",
            "Cjenici"=>"Izvoz radnih CSV i XML datoteka. Zakonska objava još nije aktivna.",
            "Lokacije"=>"Upravljajte svojim poslovnicama i prodajnim mjestima.",
            "Sinkronizacija"=>"Priprema integracija za WordPress, WooCommerce i siguran prijenos.",
            "Arhiva"=>"Pregled prethodno izrađenih radnih datoteka.",
            "Postavke"=>"Sigurnosne kopije i osnovne postavke lokalnog rada.",
            _=>"Upute, najčešća pitanja i podrška"
        };
        foreach(var nav in NavPanel.Children.OfType<Button>())
        {
            bool active=string.Equals(nav.Tag as string,page,StringComparison.Ordinal);
            nav.Background=active?new SolidColorBrush(Color.FromRgb(110,51,29)):Brush("SidebarBrush");
            nav.Foreground=active?Brush("AccentBrush"):Brush("TextBrush");
            nav.FontWeight=active?FontWeights.Bold:FontWeights.Normal;
        }
        UpdateNavigationGlyphs(page);
        PageHost.Content=page switch{
            "Pregled"=>BuildDashboard(),
            "Artikli"=>BuildCatalog("Proizvod"),
            "Usluge"=>BuildCatalog("Usluga"),
            "Povijest cijena"=>BuildHistory(),
            "Cjenici"=>BuildPricelists(),
            "Lokacije"=>BuildLocations(),
            "Sinkronizacija"=>BuildSync(),
            "Arhiva"=>BuildArchive(),
            "Postavke"=>BuildSettings(),
            _=>BuildHelp()
        };
        PageScroll.ScrollToTop();
    }

    private static Brush Brush(string key)=>(Brush)Application.Current.FindResource(key);
    private void Status(string message)=>StatusLabel.Text=message;
    private void Error(Exception ex)=>MessageBox.Show(this,ex.Message,"SIDRENA",MessageBoxButton.OK,MessageBoxImage.Warning);
    private static string Euro(long cents)=>PriceStore.FormatEur(cents)+" €";
    private static string Selected(ComboBox combo)
        => (combo.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "";
    private static string DateText(string? iso)
        => iso is not null && DateTime.TryParseExact(iso,"yyyy-MM-dd",CultureInfo.InvariantCulture,DateTimeStyles.None,out var date)
            ? date.ToString("dd. MM. yyyy.",Hr):"—";
    private static string ValueChange(long old,long now)
        => old==0?(now==0?"0,0 %":"—"):(100m*(now-old)/old).ToString("+0.0;-0.0;0.0",Hr)+" %";

    private UIElement BuildDashboard()
    {
        var outer=U.Vertical();
        var rows=_prices.ListItems(); var changes=_workspace.ListRecentChanges(6);
        var stats=U.Horizontal();
        stats.Children.Add(U.Stat("Artikala i usluga",rows.Count.ToString(Hr),"Stvarni broj lokalnih zapisa","⬡"));
        stats.Children.Add(U.Stat("Potvrđenih sidrenih",rows.Count(x=>x.AnchorCents.HasValue).ToString(Hr),
            "Nepotvrđene vrijednosti ostaju prazne","⚓"));
        stats.Children.Add(U.Stat("Promjena cijena",_workspace.ListRecentChanges(10000).Count.ToString(Hr),
            "Zabilježene lokalne izmjene","↗"));
        stats.Children.Add(U.Stat("Radnih cjenika",_workspace.ListDraftExports().Count.ToString(Hr),
            "Neobjavljeni CSV/XML izvozi","▤"));
        outer.Children.Add(stats);
        var quick=U.Horizontal();
        quick.Children.Add(U.Action("+  Novi artikl",()=>Navigate("Artikli"),true));
        quick.Children.Add(U.Action("⚒  Nova usluga",()=>Navigate("Usluge")));
        quick.Children.Add(U.Action("▤  Izradi cjenik",()=>Navigate("Cjenici")));
        quick.Children.Add(U.Action("◴  Povijest cijena",()=>Navigate("Povijest cijena")));
        outer.Children.Add(U.Card("Brze radnje",quick));
        var table=U.Grid(("Artikl / usluga","Name",0),("Vrsta","Kind",105),
            ("Prethodna","Previous",118),("Aktualna","Current",118),("Promjena","Change",98),("Datum","Time",155));
        table.ItemsSource=changes.Select(AsAuditRow).ToList();
        var history=U.Card("Nedavne promjene cijena",table,U.Action("Sve promjene →",()=>Navigate("Povijest cijena")));
        var exports=U.Vertical();
        var newest=_workspace.ListDraftExports().Take(4).ToList();
        if(newest.Count==0)exports.Children.Add(U.Notice("Još nema radnih cjenika. Izradite prvi CSV ili XML."));
        foreach(var file in newest)
            exports.Children.Add(U.Muted($"{file.CreatedUtc.ToLocalTime():dd. MM. yyyy. HH:mm}  · {file.Format}  · {Path.GetFileName(file.FilePath)}"));
        exports.Children.Add(U.Action("Otvori arhivu →",()=>Navigate("Arhiva")));
        exports.Children.Add(U.Notice("Sidrena cijena povijesna je referenca, a ne popust ili automatski 30-dnevni minimum."));
        outer.Children.Add(U.Split(history,U.Card("Status izvoza",exports)));
        return outer;
    }

    private static ItemRow AsItemRow(PriceItem item)=>new(item.Id,item.Name,item.Kind,item.Sku,item.Barcode,item.Unit,
        Euro(item.CurrentCents),item.AnchorCents.HasValue?Euro(item.AnchorCents.Value):"Nije potvrđena",DateText(item.AnchorDate));
    private static AuditRow AsAuditRow(PriceAuditView x)=>new(x.Id,x.ItemName,x.ItemKind,
        Euro(x.PreviousCents),Euro(x.CurrentCents),ValueChange(x.PreviousCents,x.CurrentCents),x.Reason,
        x.ChangedUtc.ToLocalTime().ToString("dd. MM. yyyy. HH:mm",Hr));

    private UIElement BuildCatalog(string kind)
    {
        var outer=U.Vertical();
        var filter=U.Horizontal();
        var search=U.Box("Pretražite naziv, šifru ili barkod",325);
        filter.Children.Add(U.Field("Pretraživanje",search));
        filter.Children.Add(U.Action("+  "+(kind=="Proizvod"?"Novi artikl":"Nova usluga"),
            ()=>{ PageScroll.ScrollToBottom(); },true));
        filter.Children.Add(U.Action("Uvoz CSV",()=>ShowUpcoming("CSV uvoz s provjerom sukoba")));
        outer.Children.Add(U.Card(kind=="Proizvod"?"Katalog artikala":"Katalog usluga",filter));
        var grid=U.Grid(("Naziv","Name",0),("Šifra","Sku",100),("Barkod","Barcode",130),
            ("Aktualna","Current",100),("Sidrena","Anchor",119),("Referentni datum","AnchorDate",137));
        void Reload()
        {
            var pattern=search.Text.Trim();
            grid.ItemsSource=_prices.ListItems().Where(x=>x.Kind==kind &&
                (x.Name.Contains(pattern,StringComparison.CurrentCultureIgnoreCase)
                || x.Sku.Contains(pattern,StringComparison.OrdinalIgnoreCase)
                || x.Barcode.Contains(pattern,StringComparison.OrdinalIgnoreCase)))
                .Select(AsItemRow).ToList();
        }
        search.TextChanged+=(_,_)=>Reload();
        Reload();

        var current=U.Box("Aktualna cijena",110);
        var reason=U.Box("Razlog izmjene",230);
        var changeControls=U.Horizontal();
        changeControls.Children.Add(U.Field("Nova cijena (EUR)",current));
        changeControls.Children.Add(U.Field("Razlog",reason));
        changeControls.Children.Add(U.Action("Spremi promjenu",()=>{
            if(grid.SelectedItem is not ItemRow selected)
            {Status("Odaberite artikl ili uslugu u tablici.");return;}
            try{
                _prices.UpdateCurrentPrice(selected.Id,MoneyParser.Parse(current.Text),reason.Text);
                Reload();Status("Promjena aktualne cijene trajno je zabilježena u povijesti.");
                current.Clear();reason.Clear();
            }catch(Exception ex) when(ex is ArgumentException or InvalidOperationException or Microsoft.Data.Sqlite.SqliteException){Error(ex);}
        },true));
        var left=U.Card("Popis i upravljanje cijenama",grid,U.Muted("Odaberite redak pa unesite novu aktualnu cijenu. Sidrena cijena ostaje nepromijenjena."),changeControls);
        var name=U.Box("Naziv",250);
        var sku=U.Box("Šifra",160);
        var barcode=U.Box("Barkod",160);
        var unit=U.Box("Jedinica mjere",105);unit.Text="kom";
        var actual=U.Box("Aktualna cijena",125);
        var anchor=U.Box("Opcionalna sidrena cijena",125);
        var source=U.Box("Povijesni izvor",240);
        var date=U.Choice("10. 9. 2026.","2. 5. 2025.");
        var rightFields=U.Vertical();
        rightFields.Children.Add(U.Field("Naziv "+(kind=="Proizvod"?"artikla":"usluge")+" *",name));
        var line=U.Horizontal();line.Children.Add(U.Field("Šifra",sku));line.Children.Add(U.Field("Barkod",barcode));
        rightFields.Children.Add(line);
        rightFields.Children.Add(U.Field("Jedinica mjere",unit));
        var money=U.Horizontal();money.Children.Add(U.Field("Aktualna cijena (€) *",actual));
        money.Children.Add(U.Field("Potvrđena sidrena (€)",anchor));
        rightFields.Children.Add(money);
        rightFields.Children.Add(U.Field("Referentni datum (samo uz sidrenu)",date));
        rightFields.Children.Add(U.Field("Izvor povijesne cijene",source));
        rightFields.Children.Add(U.Notice("Sidrena cijena ne izračunava se iz današnje. Datum i izvor unose se samo uz dokaziv povijesni iznos."));
        rightFields.Children.Add(U.Action("Spremi novu stavku",()=>{
            try{
                var historical=string.IsNullOrWhiteSpace(anchor.Text)?(decimal?)null:MoneyParser.Parse(anchor.Text);
                _prices.AddItem(name.Text,kind,sku.Text,barcode.Text,unit.Text,MoneyParser.Parse(actual.Text),
                    historical,historical.HasValue?(date.SelectedIndex==0?"2026-09-10":"2025-05-02"):null,
                    historical.HasValue?source.Text:null);
                name.Clear();sku.Clear();barcode.Clear();actual.Clear();anchor.Clear();source.Clear();
                Reload();Status("Stavka je spremljena u lokalni katalog.");
            }catch(Exception ex)when(ex is ArgumentException or InvalidOperationException or Microsoft.Data.Sqlite.SqliteException){Error(ex);}
        },true));
        outer.Children.Add(U.Split(left,U.Card("Nova stavka — detalji i cijene",rightFields)));
        return outer;
    }

    private UIElement BuildHistory()
    {
        var outer=U.Vertical();var search=U.Box("Pretražite artikl, uslugu ili razlog",360);
        var table=U.Grid(("Datum i vrijeme","Time",175),("Artikl / usluga","Name",0),("Prethodna","Previous",128),
            ("Aktualna","Current",128),("Promjena","Change",110),("Razlog promjene","Reason",0));
        void Reload() {
            var pattern=search.Text.Trim();
            table.ItemsSource=_workspace.ListRecentChanges(10000).Where(x=>x.ItemName.Contains(pattern,StringComparison.OrdinalIgnoreCase)||
                x.Reason.Contains(pattern,StringComparison.OrdinalIgnoreCase)).Select(AsAuditRow).ToList();
        }
        search.TextChanged+=(_,_)=>Reload();Reload();
        outer.Children.Add(U.Card("Pretraživanje povijesti",U.Field("Naziv ili razlog promjene",search)));
        var detail=U.Vertical();
        detail.Children.Add(U.Muted("Odaberite promjenu da biste vidjeli prethodni i aktualni iznos."));
        var selected=U.Text("Nema odabrane promjene",16,true);
        detail.Children.Add(selected);
        table.SelectionChanged+=(_,_)=>{
            if(table.SelectedItem is AuditRow x)
                selected.Text=$"{x.Name}\n{x.Previous}  →  {x.Current}\nPromjena: {x.Change}\n{x.Time}\nRazlog: {x.Reason}";
        };
        detail.Children.Add(U.Notice("Povijest je zapis transakcijskih promjena; ne zamjenjuje dokaz o zakonskoj referentnoj cijeni."));
        outer.Children.Add(U.Split(U.Card("Povijest cijena",table),U.Card("Detalji promjene cijene",detail)));
        return outer;
    }

    private UIElement BuildPricelists()
    {
        var outer=U.Vertical();var options=U.Horizontal();
        options.Children.Add(U.Action("Izradi radni CSV",()=>Export("CSV"),true));
        options.Children.Add(U.Action("Izradi radni XML",()=>Export("XML")));
        options.Children.Add(U.Action("Otvori arhivu",()=>Navigate("Arhiva")));
        outer.Children.Add(U.Card("Generiranje radnih cjenika",U.Notice(DraftWarning),options));
        var preview=U.Vertical();preview.Children.Add(U.Text("Pregled kataloga",16,true));
        var grid=U.Grid(("Naziv","Name",0),("Vrsta","Kind",100),("Aktualna cijena","Current",145),
            ("Sidrena cijena","Anchor",145));
        grid.ItemsSource=_prices.ListItems().Select(AsItemRow).ToList();
        preview.Children.Add(grid);
        var state=U.Vertical();
        var exports=_workspace.ListDraftExports().Take(7).ToList();
        if(exports.Count==0)state.Children.Add(U.Muted("Nema spremljenih radnih izvoza."));
        foreach(var p in exports)state.Children.Add(U.Muted($"{p.Format}  · {Path.GetFileName(p.FilePath)}"));
        state.Children.Add(U.Notice("PDF i službena javna objava bit će aktivni tek nakon provjere propisanog formata, arhive i rasporeda."));
        outer.Children.Add(U.Split(U.Card("Radni pregled cijena",preview),U.Card("Izlazne datoteke",state)));
        return outer;
    }

    private void Export(string format)
    {
        var dlg=new SaveFileDialog{
            Title="Spremi RADNI izvoz (nije javni cjenik)",
            FileName="SIDRENA_RADNI_"+DateTime.Now.ToString("yyyyMMdd_HHmm",Hr)+
                (format=="CSV"?".csv":".xml"),
            Filter=format=="CSV"?"CSV (*.csv)|*.csv":"XML (*.xml)|*.xml",
            AddExtension=true };
        if(dlg.ShowDialog(this)!=true)return;
        try{
            var items=_prices.ListItems();
            if(format=="CSV") DraftExport.Csv(items,dlg.FileName); else DraftExport.Xml(items,dlg.FileName);
            _workspace.RecordDraftExport(dlg.FileName,format,items.Count);
            Status($"Spremljen radni {format} s {items.Count} stavki. Datoteka nije službeni javni cjenik.");
            if(_activePage=="Cjenici"||_activePage=="Arhiva") Navigate(_activePage);
        }catch(Exception ex)when(ex is ArgumentException or IOException or UnauthorizedAccessException or Microsoft.Data.Sqlite.SqliteException){Error(ex);}
    }

    private UIElement BuildLocations()
    {
        var outer=U.Vertical();
        var table=U.Grid(("Poslovnica","Name",0),("Adresa","Address",0),("Grad","City",145),("Status","Status",100));
        void Reload()=>table.ItemsSource=_workspace.ListLocations()
            .Select(p=>new LocationRow(p.Id,p.Name,p.Address,p.City,p.IsActive?"Aktivna":"Neaktivna")).ToList();
        Reload();
        var name=U.Box("Naziv lokacije",240);var address=U.Box("Ulica i broj",260);var city=U.Box("Grad",160);
        var form=U.Vertical();
        form.Children.Add(U.Field("Naziv poslovnice *",name));form.Children.Add(U.Field("Adresa",address));form.Children.Add(U.Field("Grad",city));
        form.Children.Add(U.Action("+  Dodaj lokaciju",()=>{
            try{_workspace.AddLocation(name.Text,address.Text,city.Text);name.Clear();address.Clear();city.Clear();
                Reload();Status("Lokacija je spremljena.");}
            catch(Exception ex)when(ex is ArgumentException or Microsoft.Data.Sqlite.SqliteException){Error(ex);}
        },true));
        form.Children.Add(U.Action("Aktiviraj / deaktiviraj odabranu",()=>{
            if(table.SelectedItem is not LocationRow x){Status("Odaberite poslovnicu.");return;}
            try{_workspace.SetLocationActive(x.Id,x.Status!="Aktivna");Reload();Status("Status poslovnice je promijenjen.");}
            catch(Exception ex)when(ex is KeyNotFoundException or Microsoft.Data.Sqlite.SqliteException){Error(ex);}
        }));
        form.Children.Add(U.Notice("Lokacije su lokalni zapisi. Povezivanje pojedinog cjenika s poslovnicom još nije omogućeno."));
        outer.Children.Add(U.Split(U.Card("Lokacije i poslovnice",table),U.Card("Detalji lokacije",form)));
        return outer;
    }

    private UIElement BuildSync()
    {
        var outer=U.Vertical();var channels=U.Horizontal();
        foreach(string channel in new[]{"WordPress","WooCommerce","SFTP","FTPS","WebDAV","HTTPS API"})
        {
            var name=channel;
            channels.Children.Add(U.Stat(name,"Nije povezano","Integracija se priprema","↗"));
        }
        outer.Children.Add(U.Card("Kanali povezivanja",channels));
        outer.Children.Add(U.Card("Postavke povezivanja",
            U.Notice("Zbog zaštite korisničkih podataka aplikacija neće slati cijene niti čuvati pristupne podatke prije dovršetka sigurnog adaptera i provjere sukladnosti."),
            U.Action("Pogledaj službeni WordPress dodatak",()=>{
                OpenUrl("https://wordpress.org/plugins/brendigo-sidrena-cijena/");
            }),
            U.Action("Provjeri plan integracije",()=>Navigate("Pomoć"))));
        return outer;
    }

    private UIElement BuildArchive()
    {
        var outer=U.Vertical();
        var grid=U.Grid(("Radna datoteka","Name",0),("Format","Format",85),
            ("Stavki","Count",85),("Izrađeno","Date",165),("Stanje","Status",130));
        var records=_workspace.ListDraftExports();
        grid.ItemsSource=records.Select(x=>new ArchiveRow(x.Id,Path.GetFileName(x.FilePath),x.Format,
            x.ItemCount.ToString(Hr),x.CreatedUtc.ToLocalTime().ToString("dd. MM. yyyy. HH:mm",Hr),
            File.Exists(x.FilePath)?"Lokalno dostupno":"Datoteka nedostaje",x.FilePath)).ToList();
        var detail=U.Vertical();var info=U.Muted("Odaberite radnu datoteku za pregled i otvaranje.");
        detail.Children.Add(info);
        grid.SelectionChanged+=(_,_)=>{
            if(grid.SelectedItem is ArchiveRow x)
                info.Text=$"{x.Name}\n{x.Format}  ·  {x.Count} stavki\n{x.Date}\n{x.Status}";
        };
        detail.Children.Add(U.Action("Otvori odabranu datoteku",()=>{
            if(grid.SelectedItem is not ArchiveRow x){Status("Odaberite datoteku.");return;}
            if(!File.Exists(x.Path)){Status("Datoteka više nije na zabilježenoj lokaciji.");return;}
            OpenLocal(x.Path);
        },true));
        detail.Children.Add(U.Notice("Otvori pokreće lokalnu datoteku u pridruženoj aplikaciji; ne preuzima je ponovno. Samo radni izvozi, bez izmišljene javne arhive."));
        outer.Children.Add(U.Split(U.Card("Arhiva radnih izvoza",grid),U.Card("Pregled datoteke",detail)));
        return outer;
    }

    private UIElement BuildSettings()
    {
        var outer=U.Vertical();
        var data=U.Vertical();data.Children.Add(U.Muted("SQLite katalog nalazi se na korisničkom računalu."));
        data.Children.Add(U.Text(_databasePath,12));
        var actions=U.Horizontal();
        actions.Children.Add(U.Action("Izradi sigurnosnu kopiju",()=>{
            var dlg=new SaveFileDialog{Filter="SQLite baza (*.db)|*.db",FileName="SIDRENA_backup_"+DateTime.Now.ToString("yyyyMMdd_HHmm",Hr)+".db"};
            if(dlg.ShowDialog(this)!=true)return;
            try{_workspace.BackupTo(dlg.FileName);Status("Sigurnosna kopija baze uspješno je izrađena.");}
            catch(Exception ex)when(ex is IOException or UnauthorizedAccessException or ArgumentException or Microsoft.Data.Sqlite.SqliteException){Error(ex);}
        },true));
        actions.Children.Add(U.Action("Otvori mapu podataka",()=>OpenLocal(Path.GetDirectoryName(_databasePath)!)));
        data.Children.Add(actions);
        outer.Children.Add(U.Card("Lokalna baza i sigurnost",data));
        outer.Children.Add(U.Card("Opće postavke",U.Muted("Jezik: Hrvatski · Tema: Tamna · Rad bez korisničkog računa"),
            U.Notice("Šifriranje baze, automatske kopije i rasporedi još se ne predstavljaju kao aktivni. Ne postoji skrivena sinkronizacija ni telemetrija.")));
        outer.Children.Add(U.Card("Informacije o izdanju",U.Muted("SIDRENA Desktop 0.2 — razvojna verzija"),
            U.Action("Pogledaj izvorni kod",()=>OpenUrl("https://github.com/bren-wp/SIDRENA"))));
        return outer;
    }

    private UIElement BuildHelp()
    {
        return U.Card("Pomoć i podrška",
            U.Text("Brzi početak",20,true),
            U.Muted("1. Otvorite Artikli ili Usluge i unesite aktualnu cijenu."),
            U.Muted("2. Sidrenu cijenu unesite samo kad imate provjerljiv povijesni izvor."),
            U.Muted("3. Promjenu cijene zabilježite razlogom; povijest je trajno dostupna."),
            U.Muted("4. U Cjenicima izradite radni CSV ili XML i pregledajte ga u Arhivi."),
            U.Notice("Radni izvoz ne znači automatsku usklađenost sa zakonom niti se samostalno objavljuje na web."),
            U.Text("Tipkovnički prečaci",18,true),
            U.Muted("Ctrl + N — otvori artikle  ·  F1 — pomoć"),
            U.Text("Podrška",18,true),
            U.Action("Pošalji e-poštu podršci",()=>OpenUrl("mailto:sidrena@brendigo.com")));
    }

    private void ShowUpcoming(string feature)
    {
        MessageBox.Show(this,$"Opcija „{feature}” bit će omogućena nakon implementacije validacije i testiranja. Postojeći podaci neće se mijenjati.",
            "SIDRENA — u razvoju",MessageBoxButton.OK,MessageBoxImage.Information);
    }

    private void OpenLocal(string path)
    {
        try{Process.Start(new ProcessStartInfo{FileName=path,UseShellExecute=true});}
        catch(Exception ex)when(ex is System.ComponentModel.Win32Exception or IOException or InvalidOperationException){Error(ex);}
    }
    private void OpenUrl(string url)
    {
        try{Process.Start(new ProcessStartInfo{FileName=url,UseShellExecute=true});}
        catch(Exception ex)when(ex is System.ComponentModel.Win32Exception or InvalidOperationException){Error(ex);}
    }

    protected override void OnClosed(EventArgs e)
    {
        _workspace.Dispose();
        _prices.Dispose();
        base.OnClosed(e);
    }
}
