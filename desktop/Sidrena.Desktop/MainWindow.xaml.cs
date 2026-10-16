using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Text;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using Microsoft.Win32;
using Sidrena.Core;
using U=Sidrena.Desktop.UiKit;

namespace Sidrena.Desktop;

public partial class MainWindow : Window
{
    private PriceStore _prices;
    private readonly WorkspaceStore _workspace;
    private readonly string _databasePath;
    private string _activePage = "Pregled";
    private const string DraftWarning = "Cjenik je spreman za pregled i ispis. Prije javne objave provjerite podatke i primjenjive obveze.";
    private static readonly CultureInfo Hr = CultureInfo.GetCultureInfo("hr-HR");
    private sealed record ItemRow(long Id,string Name,string Kind,string Sku,string Barcode,string Unit,string Current,string Anchor,string AnchorDate);
    private sealed record AuditRow(long Id,long ItemId,string Name,string Kind,string Previous,string Current,string Change,string Reason,string Time);
    private sealed record LocationRow(long Id,string Name,string Address,string City,string Status);
    private sealed record ArchiveRow(long Id,string Name,string Format,string Count,string Date,string Status,string Path);

    public MainWindow()
    {
        InitializeComponent();
        ConfigureNavigationIcons();
        var workArea=SystemParameters.WorkArea;
        var initialSize=ResponsiveLayoutPolicy.Initial(workArea.Width,workArea.Height);
        Width=initialSize.Width;
        Height=initialSize.Height;
        WindowState=WindowState.Normal;
        Loaded+=(_,_)=>UpdateResponsiveLayout();
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
                    var pages=new[]{"Pregled","Artikli","Usluge","Povijest cijena","Cjenici",
                        "Lokacije","Sinkronizacija","Arhiva","Postavke","Pomoć"};
                    var captureFolder=Path.Combine(Path.GetDirectoryName(_databasePath)!,"ui-captures");
                    Directory.CreateDirectory(captureFolder);
                    for(int i=0;i<pages.Length;i++)
                    {
                        Navigate(pages[i]);
                        PageHost.UpdateLayout();
                        UpdateLayout();
                        var bitmap=new RenderTargetBitmap((int)Math.Ceiling(ActualWidth),
                            (int)Math.Ceiling(ActualHeight),96,96,PixelFormats.Pbgra32);
                        bitmap.Render(this);
                        var png=new PngBitmapEncoder();
                        png.Frames.Add(BitmapFrame.Create(bitmap));
                        using var output=File.Create(Path.Combine(captureFolder,$"screen-{i+1:00}.png"));
                        png.Save(output);
                    }
                    // Exercise all ten pages at two adaptive sizes, not just the catalogue.
                    var responsiveFolder=Path.Combine(Path.GetDirectoryName(_databasePath)!,"ui-captures-responsive");
                    Directory.CreateDirectory(responsiveFolder);
                    int adaptiveCount=0;
                    foreach(var size in new[]{(width:760d,height:560d,name:"compact"),
                                              (width:1060d,height:690d,name:"medium")})
                    {
                        Width=Math.Min(size.width,SystemParameters.WorkArea.Width*0.9);
                        Height=Math.Min(size.height,SystemParameters.WorkArea.Height*0.9);
                        UpdateResponsiveLayout();
                        for(int j=0;j<pages.Length;j++)
                        {
                            Navigate(pages[j]);
                            PageHost.UpdateLayout();
                            UpdateLayout();
                            if(WindowState!=WindowState.Normal ||
                               Math.Abs(SidebarColumn.ActualWidth-
                                   ResponsiveLayoutPolicy.ForWidth(ActualWidth).SidebarWidth)>2 ||
                               PageScroll.ViewportWidth<=0 || PageHost.ActualWidth<=0)
                                throw new InvalidOperationException("Adaptive layout failed: "+size.name+"/"+pages[j]);
                            var resized=new RenderTargetBitmap((int)Math.Ceiling(ActualWidth),
                                (int)Math.Ceiling(ActualHeight),96,96,PixelFormats.Pbgra32);
                            resized.Render(this);
                            var capture=new PngBitmapEncoder();
                            capture.Frames.Add(BitmapFrame.Create(resized));
                            using var file=File.Create(Path.Combine(responsiveFolder,
                                size.name+"-screen-"+(j+1).ToString("00",CultureInfo.InvariantCulture)+".png"));
                            capture.Save(file);
                            adaptiveCount++;
                        }
                    }
                    if(adaptiveCount!=20)
                        throw new InvalidOperationException("Responsive screen coverage incomplete.");
                    File.WriteAllText(marker,"SIDRENA UI screens: 10/10 passed; responsive 20/20 passed.");
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

    private void OnWindowSizeChanged(object sender,SizeChangedEventArgs e)
    {
        if(IsLoaded) UpdateResponsiveLayout();
    }

    private void UpdateResponsiveLayout()
    {
        var mode=ResponsiveLayoutPolicy.ForWidth(ActualWidth>0?ActualWidth:Width);
        SidebarColumn.Width=new GridLength(mode.SidebarWidth);
        BrandText.Visibility=mode.CompactNavigation?Visibility.Collapsed:Visibility.Visible;
        SidebarFooter.Visibility=mode.CompactNavigation?Visibility.Collapsed:Visibility.Visible;
        BrandMark.Width=mode.CompactNavigation?46:62;
        BrandMark.Height=mode.CompactNavigation?46:62;
        BrandPanel.Margin=mode.CompactNavigation?new Thickness(1,0,0,15):new Thickness(6,0,0,27);
        ContentFrame.Margin=mode.CompactNavigation?new Thickness(12,12,12,9):new Thickness(19,17,19,11);
        HeroBadge.Visibility=mode.ShowHeroBadge?Visibility.Visible:Visibility.Collapsed;
        HeroPanel.Height=mode.CompactContent?124:146;
        PageTitle.FontSize=mode.HeaderFontSize;
        PageSubtitle.FontSize=mode.CompactContent?12:14;
        foreach(var button in NavPanel.Children.OfType<Button>())
        {
            button.ToolTip=button.Tag as string;
            button.Padding=mode.CompactNavigation?new Thickness(8,11,8,11):new Thickness(16,12,16,12);
            if(button.Content is StackPanel panel)
            {
                foreach(var caption in panel.Children.OfType<TextBlock>())
                    caption.Visibility=mode.CompactNavigation?Visibility.Collapsed:Visibility.Visible;
                foreach(var icon in panel.Children.OfType<System.Windows.Shapes.Path>())
                    icon.Margin=mode.CompactNavigation?new Thickness(4,0,0,0):new Thickness(0,0,13,0);
            }
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
            "Sinkronizacija"=>"Povežite svoj WordPress i otvorite javno dostupne cjenike.",
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
        var totals=_prices.GetCatalogueTotals();
        var changes=_workspace.ListRecentChanges(6);
        outer.Children.Add(U.Metrics(
            U.Stat("Artikala i usluga",totals.Total.ToString(Hr),"Ukupno u evidenciji","⬡"),
            U.Stat("Potvrđenih sidrenih",totals.ConfirmedAnchors.ToString(Hr),
                "Nepotvrđene vrijednosti ostaju prazne","⚓"),
            U.Stat("Promjena cijena",_workspace.CountPriceChanges().ToString(Hr),
                "Evidencija promjena","↗"),
            U.Stat("Izrađenih cjenika",_workspace.CountDraftExports().ToString(Hr),
                "CSV i XML datoteke","▤")));
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
        if(newest.Count==0)exports.Children.Add(U.Notice("Još niste izradili cjenik. Odaberite Izradi cjenik za početak."));
        foreach(var file in newest)
            exports.Children.Add(U.Muted($"{file.CreatedUtc.ToLocalTime():dd. MM. yyyy. HH:mm}  · {file.Format}  · {Path.GetFileName(file.FilePath)}"));
        exports.Children.Add(U.Action("Otvori arhivu →",()=>Navigate("Arhiva")));
        exports.Children.Add(U.Notice("Sidrena cijena povijesna je referenca, a ne popust ili automatski 30-dnevni minimum."));
        outer.Children.Add(U.Split(history,U.Card("Status izvoza",exports)));
        return outer;
    }

    private static ItemRow AsItemRow(PriceItem item)=>new(item.Id,item.Name,item.Kind,item.Sku,item.Barcode,item.Unit,
        Euro(item.CurrentCents),item.AnchorCents.HasValue?Euro(item.AnchorCents.Value):"Nije potvrđena",DateText(item.AnchorDate));
    private static AuditRow AsAuditRow(PriceAuditView x)=>new(x.Id,x.ItemId,x.ItemName,x.ItemKind,
        Euro(x.PreviousCents),Euro(x.CurrentCents),ValueChange(x.PreviousCents,x.CurrentCents),x.Reason,
        x.ChangedUtc.ToLocalTime().ToString("dd. MM. yyyy. HH:mm",Hr));

    private UIElement BuildCatalog(string kind)
    {
        var outer=U.Vertical();
        var grid=U.Grid(("Naziv","Name",0),("Šifra","Sku",100),("Barkod","Barcode",130),
            ("Aktualna","Current",100),("Sidrena","Anchor",119),("Referentni datum","AnchorDate",137));
        Expander? newItemExpander=null;
        var filter=U.Horizontal();
        var search=U.Box("Pretražite naziv, šifru ili barkod",325);
        var anchorFilter=U.Choice("Sve stavke","Potvrđena sidrena","Sidrena nije potvrđena");
        filter.Children.Add(U.Field("Referentna cijena",anchorFilter));
        filter.Children.Add(U.Field("Pretraživanje",search));
        filter.Children.Add(U.Action("+  "+(kind=="Proizvod"?"Novi artikl":"Nova usluga"),
            ()=>{if(newItemExpander!=null)newItemExpander.IsExpanded=true;PageScroll.ScrollToBottom();},true));
        var count=U.Muted("Učitavanje artikala…");
        var cachedItems=_prices.ListItems();
        var searchDelay=new System.Windows.Threading.DispatcherTimer {
            Interval=TimeSpan.FromMilliseconds(180)
        };
        void Reload(bool refresh=false,long? selectId=null)
        {
            if(refresh) cachedItems=_prices.ListItems();
            var keepId=selectId??(grid.SelectedItem as ItemRow)?.Id;
            var visible=CatalogViewFilter.Apply(cachedItems,kind,search.Text,anchorFilter.SelectedIndex)
                .Select(AsItemRow).ToList();
            grid.ItemsSource=visible;
            if(keepId.HasValue)
            {
                var match=visible.FirstOrDefault(x=>x.Id==keepId.Value);
                if(match is not null) grid.SelectedItem=match;
            }
            count.Text=visible.Count.ToString(Hr)+" od "+
                cachedItems.Count(x=>x.Kind==kind).ToString(Hr)+" stavki";
        }
        search.MaxLength=256;
        searchDelay.Tick+=(_,_)=>{searchDelay.Stop();Reload();};
        search.TextChanged+=(_,_)=>{searchDelay.Stop();searchDelay.Start();};
        anchorFilter.SelectionChanged+=(_,_)=>{searchDelay.Stop();Reload();};
        outer.Unloaded+=(_,_)=>searchDelay.Stop();
        filter.Children.Add(U.Action("Očisti filtre",()=>{
            searchDelay.Stop();search.Clear();anchorFilter.SelectedIndex=0;Reload();
        }));
        filter.Children.Add(U.Action("Uvoz CSV",()=>ImportCatalogCsv(()=>Reload(refresh:true))));
        filter.Children.Add(count);
        outer.Children.Add(U.Card(kind=="Proizvod"?"Katalog artikala":"Katalog usluga",filter));
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
                Reload(refresh:true,selectId:selected.Id);
                Status("Promjena aktualne cijene trajno je zabilježena u povijesti.");
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
                var created=_prices.AddItem(name.Text,kind,sku.Text,barcode.Text,unit.Text,MoneyParser.Parse(actual.Text),
                    historical,historical.HasValue?(date.SelectedIndex==0?"2026-09-10":"2025-05-02"):null,
                    historical.HasValue?source.Text:null);
                name.Clear();sku.Clear();barcode.Clear();actual.Clear();anchor.Clear();source.Clear();
                Reload(refresh:true,selectId:created);
                Status("Stavka je spremljena u lokalni katalog.");
            }catch(Exception ex)when(ex is ArgumentException or InvalidOperationException or Microsoft.Data.Sqlite.SqliteException){Error(ex);}
        },true));
        // Reference detail drawer: edit only descriptive fields. Current/anchor prices use their own audited workflows.
        var chosen=U.Text("Odaberite redak u tablici za uređivanje podataka.",13,true);
        var eName=U.Box("Naziv odabrane stavke",260);
        var eSku=U.Box("Šifra",145);
        var eBarcode=U.Box("Barkod",165);
        var eUnit=U.Box("Jedinica",115);
        var eReason=U.Box("Razlog izmjene metapodataka",260);
        var locked=U.Muted("Sidrena cijena i aktualna cijena mogu se mijenjati samo kroz odgovarajuće postupke.");
        var editor=U.Vertical();
        editor.Children.Add(chosen);
        editor.Children.Add(U.Field("Naziv",eName));
        var editLine=U.Horizontal();
        editLine.Children.Add(U.Field("Šifra",eSku));
        editLine.Children.Add(U.Field("Barkod",eBarcode));
        editor.Children.Add(editLine);
        editor.Children.Add(U.Field("Jedinica mjere",eUnit));
        editor.Children.Add(locked);
        editor.Children.Add(U.Field("Razlog izmjene *",eReason));
        editor.Children.Add(U.Action("Spremi podatke odabrane stavke",()=>{
            if(grid.SelectedItem is not ItemRow selected){Status("Odaberite stavku u tablici.");return;}
            try
            {
                _prices.UpdateItemDetails(selected.Id,eName.Text,eSku.Text,eBarcode.Text,eUnit.Text,eReason.Text);
                Reload(refresh:true,selectId:selected.Id);eReason.Clear();
                Status("Podaci su spremljeni, a audit izmjene ostaje zabilježen. Cijene i sidrene reference nisu promijenjene.");
            }
            catch(Exception ex)when(ex is ArgumentException or KeyNotFoundException or Microsoft.Data.Sqlite.SqliteException){Error(ex);}
        },true));
        grid.SelectionChanged+=(_,_)=>{
            if(grid.SelectedItem is not ItemRow selected)return;
            var item=cachedItems.FirstOrDefault(x=>x.Id==selected.Id);
            if(item is null)return;
            chosen.Text=item.Name+"  ·  "+item.Kind;
            eName.Text=item.Name;eSku.Text=item.Sku;eBarcode.Text=item.Barcode;eUnit.Text=item.Unit;
            eReason.Clear();
            locked.Text=$"Aktualna: {Euro(item.CurrentCents)} · Sidrena: "+
                (item.AnchorCents.HasValue?Euro(item.AnchorCents.Value):"nije potvrđena")+
                $" · Datum: {DateText(item.AnchorDate)}\nPovijesni iznos i datum nisu uređivi u ovom obrascu.";
        };
        var right=U.Vertical();
        right.Children.Add(U.Card("Odabrana stavka — detalji",editor));
        newItemExpander=new Expander {
            Header="✚  Unos novog artikla ili usluge",IsExpanded=false,
            Foreground=Brush("TextBrush"),Background=Brush("SurfaceBrush"),
            Padding=new Thickness(6),Margin=new Thickness(0,0,0,15),
            Content=U.Card("Nova stavka — detalji i cijene",rightFields)
        };
        right.Children.Add(newItemExpander);
        outer.Children.Add(U.Split(left,right));
        return outer;
    }

    private void ImportCatalogCsv(Action refresh)
    {
        var dialog=new OpenFileDialog { Title="Uvezi radni SIDRENA CSV katalog",Filter="Radni CSV (*.csv)|*.csv",
            CheckFileExists=true,Multiselect=false };
        if(dialog.ShowDialog(this)!=true)return;
        try
        {
            var rows=CatalogCsv.ReadPreview(dialog.FileName);
            var review=_prices.ReviewImportRows(rows);
            var preview=string.Join("\n",rows.Take(5).Select(row=>
                $"• {row.Kind}: {row.Name} — {row.CurrentPrice.ToString("N2",Hr)} €"));
            var remaining=rows.Count>5?$"\n... i još {rows.Count-5} stavki.":"";
            var answer=MessageBox.Show(this,
                $"Provjereni CSV: {review.Total} stavki.\nProizvodi: {review.Products}, usluge: {review.Services}.\n\n"+
                preview+remaining+"\n\n"+
                "Postojeći proizvodi, cijene i povijest neće biti promijenjeni.\n"+
                "Uvoz dodaje isključivo nove stavke bez sukoba šifri ili naziva. Nastaviti?",
                "SIDRENA — potvrda uvoza",MessageBoxButton.YesNo,MessageBoxImage.Question);
            if(answer!=MessageBoxResult.Yes)return;
            int imported=_prices.ImportNewRows(rows);
            refresh();
            Status($"Uvezeno {imported} novih stavki. Postojeće cijene i sidrene reference nisu izmijenjene.");
        }
        catch(Exception ex)when(ex is ArgumentException or IOException or UnauthorizedAccessException or
            DecoderFallbackException or Microsoft.Data.Sqlite.SqliteException)
        {
            Error(ex);
        }
    }

    private UIElement BuildHistory()
    {
        var outer=U.Vertical();
        var search=U.Box("Pretražite artikl, uslugu ili razlog",360);
        var kindFilter=U.Choice("Sve vrste","Proizvodi","Usluge");
        var from=new DatePicker { Width=152, SelectedDateFormat=DatePickerFormat.Short,
            Background=Brush("InputBrush"),Foreground=Brush("TextBrush"),BorderBrush=Brush("BorderBrush") };
        var to=new DatePicker { Width=152, SelectedDateFormat=DatePickerFormat.Short,
            Background=Brush("InputBrush"),Foreground=Brush("TextBrush"),BorderBrush=Brush("BorderBrush") };
        var historySnapshot=_workspace.ListRecentChanges(10000);
        var table=U.Grid(("Datum i vrijeme","Time",175),("Artikl / usluga","Name",0),
            ("Prethodna","Previous",128),("Aktualna","Current",128),
            ("Promjena","Change",110),("Razlog promjene","Reason",0));
        IReadOnlyList<PriceAuditView> Filtered()
        {
            var phrase=search.Text.Trim();
            return historySnapshot.Where(x=>
                    (x.ItemName.Contains(phrase,StringComparison.OrdinalIgnoreCase)||
                     x.Reason.Contains(phrase,StringComparison.OrdinalIgnoreCase)) &&
                    (kindFilter.SelectedIndex switch
                     {1=>x.ItemKind=="Proizvod",2=>x.ItemKind=="Usluga",_=>true}) &&
                    (!from.SelectedDate.HasValue||x.ChangedUtc.ToLocalTime().Date>=from.SelectedDate.Value.Date) &&
                    (!to.SelectedDate.HasValue||x.ChangedUtc.ToLocalTime().Date<=to.SelectedDate.Value.Date))
                .ToList();
        }
        void Reload()=>table.ItemsSource=Filtered().Select(AsAuditRow).ToList();
        search.TextChanged+=(_,_)=>Reload();
        kindFilter.SelectionChanged+=(_,_)=>Reload();
        from.SelectedDateChanged+=(_,_)=>Reload();
        to.SelectedDateChanged+=(_,_)=>Reload();
        var filters=U.Horizontal();
        filters.Children.Add(U.Field("Vrsta zapisa",kindFilter));
        filters.Children.Add(U.Field("Od datuma",from));
        filters.Children.Add(U.Field("Do datuma",to));
        filters.Children.Add(U.Action("Očisti filtre",()=>{
            search.Clear();kindFilter.SelectedIndex=0;from.SelectedDate=null;to.SelectedDate=null;Reload();
        }));
        filters.Children.Add(U.Action("Izvezi povijest (CSV)",()=>{
            var rows=Filtered();
            if(rows.Count==0){Status("Nema zapisa za odabrane filtre.");return;}
            var dialog=new SaveFileDialog { Title="Izvoz lokalne povijesti cijena",FileName=
                "SIDRENA_POVIJEST_"+DateTime.Now.ToString("yyyyMMdd_HHmm",Hr)+".csv",
                Filter="CSV (*.csv)|*.csv",AddExtension=true };
            if(dialog.ShowDialog(this)!=true)return;
            try{DraftExport.HistoryCsv(rows,dialog.FileName);
                Status($"Izvezeno {rows.Count} stvarnih promjena cijena. Izvoz nije propisani javni cjenik.");}
            catch(Exception ex)when(ex is IOException or UnauthorizedAccessException or ArgumentException){Error(ex);}
        },true));
        Reload();
        outer.Children.Add(U.Card("Filtriranje povijesti cijena",U.Field("Naziv ili razlog promjene",search),filters));
        var detail=U.Vertical();
        detail.Children.Add(U.Muted("Odaberite promjenu da biste vidjeli prethodni i aktualni iznos."));
        var selected=U.Text("Nema odabrane promjene",16,true);
        detail.Children.Add(selected);
        var chartHost=new ContentControl{Margin=new Thickness(0,18,0,12)};
        detail.Children.Add(chartHost);
        table.SelectionChanged+=(_,_)=>{
            if(table.SelectedItem is AuditRow x)
            {
                selected.Text=$"{x.Name}\n{x.Previous}  →  {x.Current}\nPromjena: {x.Change}\n{x.Time}\nRazlog: {x.Reason}";
                var history=historySnapshot.Where(h=>h.ItemId==x.ItemId)
                    .OrderBy(h=>h.ChangedUtc).ToList();
                var values=history.Count==0?new List<long>{0}:
                    new List<long>{history[0].PreviousCents};
                values.AddRange(history.Select(h=>h.CurrentCents));
                chartHost.Content=U.PriceChart(values);
            }
        };
        detail.Children.Add(U.Notice("Povijest je zapis transakcijskih promjena; ne zamjenjuje dokaz o zakonskoj referentnoj cijeni."));
        outer.Children.Add(U.Split(U.Card("Povijest cijena",table),U.Card("Detalji promjene cijene",detail)));
        return outer;
    }

    private UIElement BuildPricelists()
    {
        var outer=U.Vertical();
        var preview=U.Vertical();
        preview.Children.Add(U.Text("Pregled kataloga",16,true));
        var grid=U.Grid(("Naziv","Name",0),("Vrsta","Kind",100),("Aktualna cijena","Current",145),
            ("Sidrena cijena","Anchor",145));
        var kind=U.Choice("Svi artikli i usluge","Samo artikli","Samo usluge");
        List<PriceItem> SelectedItems()=>
            _prices.ListItems().Where(x=>kind.SelectedIndex switch
                {1=>x.Kind=="Proizvod",2=>x.Kind=="Usluga",_=>true}).ToList();
        void Refresh()=>grid.ItemsSource=SelectedItems().Select(AsItemRow).ToList();
        kind.SelectionChanged+=(_,_)=>Refresh();
        Refresh();
        var options=U.Horizontal();
        options.Children.Add(U.Field("Obuhvat cjenika",kind));
        options.Children.Add(U.Action("Izradi radni CSV",()=>Export("CSV",SelectedItems()),true));
        options.Children.Add(U.Action("Izradi radni XML",()=>Export("XML",SelectedItems())));
        options.Children.Add(U.Action("Pregled i ispis (HTML)",()=>{
            var selected=SelectedItems();
            if(selected.Count==0){Status("Odabrani obuhvat nema artikala ili usluga.");return;}
            try
            {
                var folder=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "Brendigo","SIDRENA","pregledi");
                var path=Path.Combine(folder,"SIDRENA_RADNI_PREGLED_"+
                    DateTime.Now.ToString("yyyyMMdd_HHmmss_fff",Hr)+".html");
                var scope=kind.SelectedIndex switch
                {1=>"artikli",2=>"usluge",_=>"artikli i usluge"};
                LocalPricelistPreview.Write(selected,path,"SIDRENA — radni pregled: "+scope);
                OpenLocal(path);
                Status("Otvoren lokalni pregled za ispis ili spremanje u PDF putem preglednika. Nije službeni cjenik.");
            }
            catch(Exception ex)when(ex is IOException or UnauthorizedAccessException or ArgumentException){Error(ex);}
        }));
        options.Children.Add(U.Action("Otvori arhivu",()=>Navigate("Arhiva")));
        outer.Children.Add(U.Card("Generiranje radnih cjenika",U.Notice(DraftWarning),options));
        preview.Children.Add(grid);
        var state=U.Vertical();
        var exports=_workspace.ListDraftExports().Take(7).ToList();
        if(exports.Count==0)state.Children.Add(U.Muted("Nema spremljenih radnih izvoza."));
        foreach(var p in exports)state.Children.Add(U.Muted($"{p.Format}  · {Path.GetFileName(p.FilePath)}"));
        state.Children.Add(U.Notice("PDF preko preglednika i službena javna objava nisu isto. Javni zakonski serializer još nije aktiviran."));
        outer.Children.Add(U.Split(U.Card("Radni pregled cijena",preview),U.Card("Izlazne datoteke",state)));
        return outer;
    }

    private void Export(string format,IReadOnlyList<PriceItem>? selection=null)
    {
        var items=selection ?? _prices.ListItems();
        if(items.Count==0){Status("Odabrani obuhvat nema artikala ili usluga.");return;}
        var dlg=new SaveFileDialog{
            Title="Spremi RADNI izvoz (nije javni cjenik)",
            FileName="SIDRENA_RADNI_"+DateTime.Now.ToString("yyyyMMdd_HHmm",Hr)+
                (format=="CSV"?".csv":".xml"),
            Filter=format=="CSV"?"CSV (*.csv)|*.csv":"XML (*.xml)|*.xml",
            AddExtension=true };
        if(dlg.ShowDialog(this)!=true)return;
        try{
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
        var outer=U.Vertical();
        outer.Children.Add(U.Card("WordPress i vaše poslovnice",
            U.Text("Brendigo Sidrena Cijena 1.5.0",18,true),
            U.Muted("Učitajte javne cjenike i povijest objava povezane web-stranice.")));

        var site=U.Box("https://primjer.hr/",420);
        site.Text=_workspace.GetOfficialSite()??"";
        var connectionStatus=U.Text("Povežite svoju WordPress stranicu.",15,true);
        var published=U.Muted("Podaci nisu učitani.");
        var feeds=U.Vertical();
        Uri? verifiedCjenik=null;
        Uri? verifiedArchive=null;
        var controls=U.Horizontal();
        controls.Children.Add(U.Action("Provjeri povezanost",async ()=>{
            verifiedCjenik=null;verifiedArchive=null;
            connectionStatus.Text="Provjeravam javno dostupne metapodatke…";
            published.Text="Učitavanje bez prijenosa lokalnih cijena i bez prijave.";
            feeds.Children.Clear();
            try
            {
                var result=await OfficialPluginDiscovery.FetchAsync(site.Text);
                verifiedCjenik=result.PublicPricelist;
                verifiedArchive=result.PublicArchive;
                connectionStatus.Text="WordPress stranica je dostupna";
                published.Text="Posljednja objava: "+(result.PublishedAt.HasValue?
                    result.PublishedAt.Value.ToLocalTime().ToString("dd. MM. yyyy. HH:mm",Hr):
                    "nije evidentirana")+$"\nDostupna su {result.Feeds.Count} tipizirana izvora i {result.OutletCount} poslovnica.";
                foreach(var item in result.Feeds)
                    feeds.Children.Add(U.Action("Otvori javni "+item.Kind+" "+item.Format.ToUpperInvariant(),
                        ()=>OpenUrl(item.Url.AbsoluteUri)));
                if(result.CombinedFeed is not null)
                    feeds.Children.Add(U.Action("Objedinjeni javni izvor",()=>OpenUrl(result.CombinedFeed.AbsoluteUri)));
                if(result.ArchiveApi is not null)
                    feeds.Children.Add(U.Action("Pregled arhive",()=>OpenUrl(result.ArchiveApi.AbsoluteUri)));
                if(result.Outlets.Count>0)
                {
                    feeds.Children.Add(U.Text("Javni izvori po poslovnici",15,true));
                    foreach(var outlet in result.Outlets)
                    {
                        feeds.Children.Add(U.Muted(outlet.Name+"  ·  oznaka "+outlet.Code));
                        foreach(var feed in outlet.Feeds)
                            feeds.Children.Add(U.Action("Otvori "+outlet.Code+" · "+feed.Kind+" "+
                                feed.Format.ToUpperInvariant(),()=>OpenUrl(feed.Url.AbsoluteUri)));
                    }
                }
                Status("Javni REST metapodaci dostupni (samo čitanje); Windows aplikacija nije ovlaštena za objavu.");
            }
            catch(Exception ex)when(ex is ArgumentException or HttpRequestException or InvalidDataException or
                System.Text.Json.JsonException or TaskCanceledException)
            {
                connectionStatus.Text="Nije moguće provjeriti službeni API.";
                published.Text=ex.Message;
                Status("Provjera javnog WordPress REST API-ja nije uspjela.");
            }
        },true));
        controls.Children.Add(U.Action("Spremi adresu",()=>{
            try
            {
                _workspace.SaveOfficialSite(site.Text);
                Status("Web-stranica je spremljena.");
            }
            catch(ArgumentException ex){Error(ex);}
        }));
        controls.Children.Add(U.Action("Zaboravi adresu",()=>{
            _workspace.ClearOfficialSite();site.Clear();
            verifiedCjenik=null;verifiedArchive=null;
            connectionStatus.Text="Javna WordPress adresa uklonjena.";
            published.Text="Podaci nisu učitani.";
            feeds.Children.Clear();
            Status("Spremljena WordPress adresa je uklonjena.");
        }));
        controls.Children.Add(U.Action("Otvori javni cjenik",()=>{
            if(verifiedCjenik is null){Status("Najprije provjerite javni API.");return;}
            OpenUrl(verifiedCjenik.AbsoluteUri);
        }));
        controls.Children.Add(U.Action("Otvori javnu arhivu",()=>{
            if(verifiedArchive is null){Status("Najprije provjerite javni API.");return;}
            OpenUrl(verifiedArchive.AbsoluteUri);
        }));
        outer.Children.Add(U.Card("Povezivanje sa službenim WordPress dodatkom",
            U.Notice("Sigurno provjerite javno objavljene cjenike, arhivu i poslovnice svojeg WordPress dodatka. Za provjeru nije potrebna lozinka."),
            U.Field("Adresa web-stranice sa službenim dodatkom",site),
            controls,connectionStatus,published,
            U.Muted("Dostupni cjenici:"),feeds));
        outer.Children.Add(U.Card("Automatska objava i sinkronizacija",
            U.Notice("Pregled javnih sadržaja ne mijenja podatke na vašoj web-stranici. Automatsko objavljivanje nije dostupno u ovoj verziji."),
            U.Action("Službeni WordPress dodatak",()=>
                OpenUrl("https://wordpress.org/plugins/brendigo-sidrena-cijena/"))));
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
            try
            {
                if(!_workspace.VerifyDraftExport(x.Id))
                {
                    var decision=MessageBox.Show(this,
                        "Lokalna datoteka se promijenila nakon evidentiranja. Želite li je ipak otvoriti?"+
                        "\nIzvorna SHA-256 provjera više ne odgovara.",
                        "SIDRENA — datoteka je izmijenjena",MessageBoxButton.YesNo,MessageBoxImage.Warning);
                    if(decision!=MessageBoxResult.Yes)return;
                }
                OpenLocal(x.Path);
            }
            catch(Exception ex)when(ex is IOException or UnauthorizedAccessException or KeyNotFoundException)
            {Error(ex);}
        },true));
        detail.Children.Add(U.Notice("Otvori pokreće lokalnu datoteku u pridruženoj aplikaciji; ne preuzima je ponovno. Samo radni izvozi, bez izmišljene javne arhive."));
        outer.Children.Add(U.Split(U.Card("Arhiva radnih izvoza",grid),U.Card("Pregled datoteke",detail)));
        return outer;
    }

    private UIElement BuildSettings()
    {
        var outer=U.Vertical();
        var data=U.Vertical();
        data.Children.Add(U.Muted("Zaštitite svoju evidenciju sigurnosnom kopijom. Podatke možete vratiti kada god zatreba."));
        var actions=U.Horizontal();
        actions.Children.Add(U.Action("Izradi sigurnosnu kopiju",()=>{
            var dlg=new SaveFileDialog{Filter="Sigurnosna kopija SIDRENA (*.db)|*.db",FileName="SIDRENA_backup_"+DateTime.Now.ToString("yyyyMMdd_HHmm",Hr)+".db"};
            if(dlg.ShowDialog(this)!=true)return;
            try{_workspace.BackupTo(dlg.FileName);Status("Sigurnosna kopija uspješno je izrađena.");}
            catch(Exception ex)when(ex is IOException or UnauthorizedAccessException or ArgumentException or Microsoft.Data.Sqlite.SqliteException){Error(ex);}
        },true));
        actions.Children.Add(U.Action("Provjeri i vrati sigurnosnu kopiju",()=>{
            var dlg=new OpenFileDialog{Title="Odaberi prethodno spremljenu SIDRENA sigurnosnu kopiju",
                Filter="Sigurnosna kopija SIDRENA (*.db)|*.db",CheckFileExists=true,Multiselect=false};
            if(dlg.ShowDialog(this)!=true)return;
            try
            {
                var source=Path.GetFullPath(dlg.FileName);
                var inspection=WorkspaceStore.InspectBackup(source);
                var question=$"Kopija: {Path.GetFileName(source)}\n"+
                    $"Proizvodi: {inspection.Products}, usluge: {inspection.Services}\n"+
                    $"Promjene cijena: {inspection.PriceChanges}, poslovnice: {inspection.Locations}\n\n"+
                    "Postojeći podaci bit će zamijenjeni odabranom kopijom.\n"+
                    "Prije povrata automatski će se izraditi zasebna zaštitna kopija postojećih podataka.\n"+
                    "Želite li nastaviti?";
                if(MessageBox.Show(this,question,"SIDRENA — potvrda vraćanja podataka",
                    MessageBoxButton.YesNo,MessageBoxImage.Warning)!=MessageBoxResult.Yes)return;
                var recoveryDir=Path.Combine(Path.GetDirectoryName(_databasePath)!,"backups");
                var recovery=Path.Combine(recoveryDir,
                    "SIDRENA_prije_povrata_"+DateTime.Now.ToString("yyyyMMdd_HHmmss",Hr)+
                    "_"+Guid.NewGuid().ToString("N")[..8]+".db");
                // The other open SQLite connection must not hold locks during online restore.
                _prices.Dispose();
                try
                {
                    _workspace.RestoreFrom(source,recovery);
                }
                finally
                {
                    // Reopen and rebind all view models to the actual restored database.
                    _prices=new PriceStore(_databasePath);
                    Navigate("Pregled");
                }
                Status("Podaci su uspješno vraćeni. Prethodno stanje sigurno je spremljeno.");
                MessageBox.Show(this,"Podaci su uspješno vraćeni. Sačuvana je i sigurnosna kopija prethodnog stanja.","SIDRENA — povrat dovršen",MessageBoxButton.OK,MessageBoxImage.Information);
            }
            catch(Exception ex)when(ex is IOException or UnauthorizedAccessException or ArgumentException or
                InvalidDataException or InvalidOperationException or AggregateException or Microsoft.Data.Sqlite.SqliteException)
            {Error(ex);}
        }));

        data.Children.Add(actions);
        outer.Children.Add(U.Card("Sigurnost i zaštitne kopije",data));
        outer.Children.Add(U.Card("Opće postavke",U.Muted("Jezik: Hrvatski · Izgled: Tamni · Jednostavno upravljanje"),
            U.Notice("Vaše poslovne podatke možete dodatno zaštititi izradom redovitih sigurnosnih kopija.")));
        outer.Children.Add(U.Card("Informacije o izdanju",
            U.Text("SIDRENA Desktop 0.9.0",18,true),
            U.Muted("Windows 10/11 · Brendigo LTD · Komercijalna licencija"),
            U.Notice("Povezani WordPress dodatak Brendigo Sidrena Cijena zadržava verziju 1.5.0."),
            U.Action("Preuzmi najnoviju Windows aplikaciju",()=>
                OpenUrl("https://github.com/bren-wp/SIDRENA/releases/tag/1.5.0"),true),
            U.Action("Uvjeti komercijalne licence",ShowCommercialLicense),
            U.Action("Informacije o programu",()=>OpenUrl("https://brendigo.com/"))));
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
            U.Text("Preuzimanje i ažuriranja",18,true),
            U.Muted("Setup EXE instalira SIDRENA-u, portable EXE radi bez instalacije. Baza se pri ažuriranju čuva."),
            U.Action("Otvori Windows preuzimanja",()=>OpenUrl("https://github.com/bren-wp/SIDRENA/releases/tag/1.5.0")),
            U.Text("Pravne informacije",18,true),
            U.Muted("Nositelj prava na Windows aplikaciju: Brendigo LTD."),
            U.Action("Pročitaj licencu",ShowCommercialLicense),
            U.Text("Podrška",18,true),
            U.Action("Pošalji e-poštu podršci",()=>OpenUrl("mailto:sidrena@brendigo.com")));
    }

    private void ShowCommercialLicense()
    {
        const string resource="Sidrena.Desktop.Licensing.EULA-HR.txt";
        using var stream=typeof(MainWindow).Assembly.GetManifestResourceStream(resource);
        if(stream is null)
        {
            MessageBox.Show(this,"Uvjeti licence trenutačno nisu dostupni. Obratite se podršci.",
                "SIDRENA — licencija",MessageBoxButton.OK,MessageBoxImage.Warning);
            return;
        }
        using var reader=new StreamReader(stream,Encoding.UTF8);
        var window=new Window
        {
            Title="SIDRENA — komercijalna licencija",Owner=this,
            WindowStartupLocation=WindowStartupLocation.CenterOwner,
            Width=Math.Min(800,SystemParameters.WorkArea.Width*0.86),
            Height=Math.Min(670,SystemParameters.WorkArea.Height*0.86),
            MinWidth=420,MinHeight=300,ShowInTaskbar=false,
            Background=Brush("BackgroundBrush"),FontFamily=FontFamily
        };
        var root=new DockPanel{Margin=new Thickness(22)};
        var close=U.Action("Zatvori",()=>window.Close(),true);
        DockPanel.SetDock(close,Dock.Bottom);
        root.Children.Add(close);
        root.Children.Add(new TextBox{
            Text=reader.ReadToEnd(),IsReadOnly=true,AcceptsReturn=true,
            TextWrapping=TextWrapping.Wrap,
            VerticalScrollBarVisibility=ScrollBarVisibility.Auto,
            HorizontalScrollBarVisibility=ScrollBarVisibility.Disabled,
            Padding=new Thickness(18),Margin=new Thickness(0,0,0,16)
        });
        window.Content=root;
        window.ShowDialog();
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
