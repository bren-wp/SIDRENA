using Sidrena.Core;

int passed = 0;
var folder = Path.Combine(Path.GetTempPath(), "sidrena-tests-" + Guid.NewGuid().ToString("N"));
Directory.CreateDirectory(folder);
try
{
    var file = Path.Combine(folder, "catalogue.db");
    long id;
    using (var store = new PriceStore(file))
    {
        id = store.AddItem("Mlijeko; \"punomasno\"", "Proizvod", "SKU-1", "3851234567890", "L",
            2.35m, 2.10m, "2026-09-10", "Račun iz arhive");
        var pending = store.AddItem("Usluga šišanja", "Usluga", "", "", "kom", 15m, null, null, null);
        Expect(store.ListItems().Count == 2, "Insert item");
        Expect(store.ListItems().First(p => p.Id == pending).AnchorCents is null, "No fabricated reference");
        store.UpdateCurrentPrice(id, 2.49m, "Novi cjenik");
        store.UpdateCurrentPrice(id, 2.49m, "Bez promjene");
        Expect(store.ListChanges(id).Count == 1, "Idempotent price log");
        var product = store.ListItems().Single(p => p.Id == id);
        Expect(product.CurrentCents == 249, "Current price updated");
        Expect(product.AnchorCents == 210 && product.AnchorDate == "2026-09-10", "Anchor unchanged");
        MustThrow(() => store.AddItem("Bez izvora", "Proizvod", "", "", "", 1m, 1m, "2026-09-10", null));
        MustThrow(() => store.AddItem("Pogrešan datum", "Proizvod", "", "", "", 1m, 1m, "2026-09-11", "Izvor"));
        MustThrow(() => store.AddItem("Preciznost", "Usluga", "", "", "", 1.123m, null, null, null));
        Expect(MoneyParser.Parse("12,50") == 12.50m, "Croatian decimal comma");
        Expect(MoneyParser.Parse("12.50") == 12.50m, "Invariant decimal dot");
        MustThrow(() => MoneyParser.Parse("1,234.56"));
        MustThrow(() => MoneyParser.Parse("12.500"));
        var csv = Path.Combine(folder, "RADNI.csv");
        var xml = Path.Combine(folder, "RADNI.xml");
        DraftExport.Csv(store.ListItems(), csv);
        DraftExport.Xml(store.ListItems(), xml);
        Expect(File.ReadAllText(csv).Contains("\"Mlijeko; \"\"punomasno\"\"\""), "CSV quoting");
        Expect(File.ReadAllText(xml).Contains("SidrenaRadniIzvoz"), "XML draft marker");
    }
    using (var workspace = new WorkspaceStore(file))
    {
        var loc = workspace.AddLocation("Poslovnica Rijeka", "Korzo 1", "Rijeka");
        Expect(workspace.ListLocations().Count == 1, "Persist local locations");
        workspace.SetLocationActive(loc, false);
        Expect(!workspace.ListLocations()[0].IsActive, "Toggle location status");
        Expect(workspace.ListRecentChanges().Count == 1, "Joined price audit history");
        Expect(workspace.GetOfficialSite() is null,"No default web connection");
        workspace.SaveOfficialSite("https://sidrena.example.hr");
        Expect(workspace.GetOfficialSite()=="https://sidrena.example.hr","Only public site URL stored");
        MustThrow(()=>workspace.SaveOfficialSite("http://insecure.example.hr"));
        Expect(workspace.GetOfficialSite()=="https://sidrena.example.hr","Rejected URL doesn't overwrite saved site");
        workspace.ClearOfficialSite();
        Expect(workspace.GetOfficialSite() is null,"Official site can be forgotten");
        var historyPath=Path.Combine(folder,"povijest.csv");
        DraftExport.HistoryCsv(workspace.ListRecentChanges(),historyPath);
        Expect(File.ReadAllText(historyPath).Contains("DATUM_I_VRIJEME") &&
               File.ReadAllText(historyPath).Contains("Novi cjenik"),"Real audit CSV export");
        var csv = Path.Combine(folder, "RADNI.csv");
        workspace.RecordDraftExport(csv, "CSV", 2);
        var drafts = workspace.ListDraftExports();
        Expect(drafts.Count == 1 && drafts[0].Sha256.Length == 64, "SHA256 draft record");
        Expect(workspace.VerifyDraftExport(drafts[0].Id), "Original archive passes integrity check");
        File.AppendAllText(csv, "\n");
        Expect(!workspace.VerifyDraftExport(drafts[0].Id), "Changed export detected by checksum");
        MustThrow(() => workspace.RecordDraftExport(csv, "PDF", 2));
        var backup = Path.Combine(folder, "backup.db");
        workspace.BackupTo(backup);
        using var backupPrices = new PriceStore(backup);
        Expect(backupPrices.ListItems().Count == 2, "Online backup includes WAL data");
        using var backupWorkspace = new WorkspaceStore(backup);
        Expect(backupWorkspace.ListLocations().Count == 1, "Online backup includes workspace");
        MustThrow(() => workspace.BackupTo(file));
    }
    // Restore a WAL-consistent snapshotPath. Invalid backup must never overwrite active data.
    var recoveryDb=Path.Combine(folder,"restore-verify.db");
    var snapshotPath=Path.Combine(folder,"restore-snapshotPath.db");
    var safety=Path.Combine(folder,"restore-safety.db");
    using(var verifyPrices=new PriceStore(recoveryDb))
    {
        var item=verifyPrices.AddItem("Stara cijena", "Proizvod", "REC-1", "", "kom",
            7.25m,6.95m,"2026-09-10","Račun 123");
        using(var verifyWorkspace=new WorkspaceStore(recoveryDb))
        {
            verifyWorkspace.AddLocation("Trgovina","Korzo 15","Rijeka");
            verifyWorkspace.BackupTo(snapshotPath);
            var inspection=WorkspaceStore.InspectBackup(snapshotPath);
            Expect(inspection.Products==1&&inspection.Locations==1,"Read-only backup preview");
            Expect(inspection.PriceChanges==0,"Backup preview keeps original audit");
        }
        verifyPrices.UpdateCurrentPrice(item,8.50m,"Promjena nakon kopije");
    }
    File.WriteAllText(Path.Combine(folder,"not-sqlite.db"),"invalid data");
    using(var restoreWorkspace=new WorkspaceStore(recoveryDb))
    {
        MustThrowType<InvalidDataException>(()=>
            WorkspaceStore.InspectBackup(Path.Combine(folder,"not-sqlite.db")));
        using(var nonSidrena=new Microsoft.Data.Sqlite.SqliteConnection(
            new Microsoft.Data.Sqlite.SqliteConnectionStringBuilder{
                DataSource=Path.Combine(folder,"other.db"),Pooling=false}.ToString()))
        {
            nonSidrena.Open();
            using var sql=nonSidrena.CreateCommand();
            sql.CommandText="CREATE TABLE other_data(id INTEGER)";
            sql.ExecuteNonQuery();
        }
        MustThrowType<InvalidDataException>(()=>
            WorkspaceStore.InspectBackup(Path.Combine(folder,"other.db")));
        Expect(WorkspaceStore.InspectBackup(snapshotPath).Products==1,"Original backup stays unchanged");
        MustThrowType<InvalidDataException>(()=>
            restoreWorkspace.RestoreFrom(Path.Combine(folder,"not-sqlite.db"),safety));
        Expect(!File.Exists(safety),"Invalid restore never creates safety copy or touches live database");
        var protectedPath=restoreWorkspace.RestoreFrom(snapshotPath,safety);
        Expect(protectedPath==safety&&File.Exists(safety),"Restore creates recoverable pre-restore snapshotPath");
        Expect(WorkspaceStore.InspectBackup(safety).PriceChanges==1,"Recovery snapshotPath includes later WAL transaction");
        Expect(restoreWorkspace.ListLocations().Count==1,"Restored workspace locations");
    }
    using(var restored=new PriceStore(recoveryDb))
    {
        Expect(restored.ListItems().Single().CurrentCents==725,"Restore brings back original actual price");
        Expect(restored.ListItems().Single().AnchorCents==695&&
               restored.ListItems().Single().AnchorDate=="2026-09-10","Restore retains original anchor");
        Expect(restored.ListChanges(restored.ListItems().Single().Id).Count==0,"Restore preserves earlier audit history");
    }
    using(var safetyPrices=new PriceStore(safety))
    {
        Expect(safetyPrices.ListItems().Single().CurrentCents==850,"Safety copy preserves pre-restore price");
        Expect(safetyPrices.ListChanges(safetyPrices.ListItems().Single().Id).Count==1,"Safety copy preserves WAL audit");
    }

    using (var reopened = new PriceStore(file))
    {
        Expect(reopened.ListItems().Count == 2, "Durable database");
        Expect(reopened.ListChanges(id).Count == 1, "Durable audit trail");
    }
    // Catalog editing must never alter the historical reference or current price.
    using (var editable = new PriceStore(file))
    {
        editable.UpdateItemDetails(id, "Mlijeko punomasno", "SKU-EDIT", "3851234567890", "l", "Ispravak naziva");
        var edited = editable.ListItems().Single(x => x.Id == id);
        Expect(edited.Name == "Mlijeko punomasno" && edited.Sku == "SKU-EDIT", "Metadata changes persisted");
        Expect(edited.CurrentCents == 249 && edited.AnchorCents == 210, "Edit preserves price and anchor");
        Expect(editable.ListMetadataChanges(id).Count == 1, "Metadata has its own audit");
        editable.UpdateItemDetails(id, "Mlijeko punomasno", "SKU-EDIT", "3851234567890", "l", "Bez promjene");
        Expect(editable.ListMetadataChanges(id).Count == 1, "Unchanged metadata does not create audit");
        MustThrow(() => editable.UpdateItemDetails(id, "", "sku", "", "", "Invalid name"));
        Expect(editable.ListItems().Single(x => x.Id == id).Name == "Mlijeko punomasno", "Invalid edit rolled back");
        editable.AddItem("Drugi proizvod", "Proizvod", "OTHER", "", "kom", 1m, null, null, null);
        MustThrow(() => editable.UpdateItemDetails(id, "Mlijeko", "other", "", "l", "Sukob"));
        Expect(editable.ListItems().Single(x => x.Id == id).Sku == "SKU-EDIT", "Duplicate SKU cannot overwrite");
    }
    // Import preview must preserve quoted delimiters and be all-or-nothing.
    using (var source = new PriceStore(Path.Combine(folder, "source.db")))
    {
        source.AddItem("Kruh; \"tost\"", "Proizvod", "BREAD-10", "", "kom",
            2.49m, 1.99m, "2026-09-10", "Fiskalni račun");
        source.AddItem("Šišanje", "Usluga", "SERVICE-1", "", "kom", 15m, null, null, null);
        var sourceCsv = Path.Combine(folder, "source.csv");
        DraftExport.Csv(source.ListItems(), sourceCsv);
        var preview = CatalogCsv.ReadPreview(sourceCsv);
        Expect(preview.Count == 2, "Preview count");
        Expect(preview.Any(x => x.Name == "Kruh; \"tost\""), "CSV preview quoted delimiters");
        Expect(preview.Single(x => x.Sku == "BREAD-10").AnchorPrice == 1.99m, "Read anchor without fabrication");
        using var destination = new PriceStore(Path.Combine(folder, "destination.db"));
        Expect(destination.ImportNewRows(preview) == 2, "Transactional import inserted both rows");
        Expect(destination.ListItems().Count == 2, "Imported catalogue count");
        MustThrow(() => destination.ImportNewRows(preview));
        Expect(destination.ListItems().Count == 2, "Duplicate import leaves catalogue untouched");
        var two = new List<CatalogImportRow>{
            new("Usluga","Dodatna usluga","TEMP-1","","kom",12m,null,null,null),
            new("Proizvod","Sukob", "BREAD-10", "", "kom",2m,null,null,null)
        };
        MustThrow(() => destination.ImportNewRows(two));
        Expect(destination.ListItems().Count == 2, "Batch collision rolls back all");
        var invalid = new List<CatalogImportRow>{
            new("Proizvod","Nova", "TEST-1", "", "kom",2m,null,null,null),
            new("Proizvod","Lažni povijesni datum", "TEST-2", "", "kom",2m,1m,"2025-10-15","Nepoznato")
        };
        MustThrow(() => destination.ImportNewRows(invalid));
        Expect(destination.ListItems().Count == 2, "Invalid anchor rolls back entire batch");
        File.WriteAllText(Path.Combine(folder, "invalid.csv"), "VRSTA;NAZIV;SIFRA\nProizvod;Mlijeko;SKU");
        MustThrow(() => CatalogCsv.ReadPreview(Path.Combine(folder, "invalid.csv")));
        File.WriteAllText(Path.Combine(folder, "broken.csv"),
            CatalogCsv.Header+"\n\"Proizvod;Unclosed;SKU\n");
        MustThrow(() => CatalogCsv.ReadPreview(Path.Combine(folder, "broken.csv")));
        source.AddItem("=CMD", "Proizvod", "+RISK", "", "kom", 1m, null, null, null);
        source.AddItem("'Legitimno", "Proizvod", "ESCAPED", "", "kom", 1m, null, null, null);
        DraftExport.Csv(source.ListItems(), sourceCsv);
        var protectedCsv=File.ReadAllText(sourceCsv);
        Expect(protectedCsv.Contains("\"'=CMD\""), "CSV guards spreadsheet formula execution");
        Expect(protectedCsv.Contains("\"'+RISK\""), "CSV protects formula-like SKU");
        var protectedRows=CatalogCsv.ReadPreview(sourceCsv);
        Expect(protectedRows.Any(x=>x.Name=="=CMD" && x.Sku=="+RISK"), "Guarded CSV round-trips risky text");
        Expect(protectedRows.Any(x=>x.Name=="'Legitimno"), "Original apostrophe preserved");
        MustThrow(() => destination.ImportNewRows(new[]{
            new CatalogImportRow("Usluga","Bez šifre","","","kom",1m,null,null,null),
            new CatalogImportRow("Usluga","bez ŠIFRE","","","kom",2m,null,null,null)
        }));
        Expect(destination.ListItems().Count==2,"Duplicate empty SKU names rejected transactionally");
        var htmlPath=Path.Combine(folder,"radni-pregled.html");
        var unsafeRow=new CatalogImportRow("Proizvod","<script>alert(1)</script>","CODE-HTML","","kom",2m,null,null,null);
        var previewData=new PriceItem(9000,unsafeRow.Name,unsafeRow.Kind,unsafeRow.Sku,"","kom",
            200,null,null,null,DateTimeOffset.UtcNow);
        LocalPricelistPreview.Write(new[]{previewData},htmlPath);
        var markup=File.ReadAllText(htmlPath);
        Expect(markup.Contains("&lt;script&gt;alert(1)&lt;/script&gt;"),"HTML preview escapes product text");
        Expect(!markup.Contains("<script>"),"HTML preview never emits database markup as executable script");
        Expect(markup.Contains("default-src 'none'"),"HTML preview remains offline-only with CSP");
        Expect(markup.Contains("NIJE SLUŽBENI JAVNI CJENIK"),"Print preview explicitly marked non-statutory");
    }
    // Public read-only integration with the exact WordPress.org 1.5.0 API contract.
    var endpoint=OfficialPluginDiscovery.Endpoint("https://sidrena.example.hr/trgovina");
    Expect(endpoint.AbsoluteUri=="https://sidrena.example.hr/trgovina/wp-json/brendigo-sidrena/v1/cjenici",
        "Nested WordPress installation REST endpoint");
    MustThrow(()=>OfficialPluginDiscovery.Endpoint("http://sidrena.example.hr"));
    MustThrow(()=>OfficialPluginDiscovery.Endpoint("https://name:secret@sidrena.example.hr"));
    MustThrow(()=>OfficialPluginDiscovery.Endpoint("https://localhost"));
    const string restJson="""
    {
      "published_at":"2026-10-16T16:22:00+00:00",
      "cjenik":"https://sidrena.example.hr/javni-cjenik",
      "archive":"https://sidrena.example.hr/arhiva",
      "type_archive":"https://sidrena.example.hr/wp-json/brendigo-sidrena/v1/arhiva",
      "combined":"https://sidrena.example.hr/sidrena-cijena.xml",
      "feeds":{
        "proizvodi":{
          "csv":"https://sidrena.example.hr/wp-json/brendigo-sidrena/v1/cjenici/proizvodi/csv",
          "xml":"https://sidrena.example.hr/wp-json/brendigo-sidrena/v1/cjenici/proizvodi/xml"
        },
        "usluge":{
          "csv":"https://sidrena.example.hr/wp-json/brendigo-sidrena/v1/cjenici/usluge/csv",
          "xml":"https://sidrena.example.hr/wp-json/brendigo-sidrena/v1/cjenici/usluge/xml"
        }
      },
      "outlets":[{
        "name":"Rijeka Centar","code":"RJK",
        "feeds":{
          "proizvodi":{
            "csv":"https://sidrena.example.hr/wp-json/brendigo-sidrena/v1/cjenici/proizvodi/csv?oznaka=RJK",
            "xml":"https://sidrena.example.hr/wp-json/brendigo-sidrena/v1/cjenici/proizvodi/xml?oznaka=RJK"
          },
          "usluge":{
            "csv":"https://sidrena.example.hr/wp-json/brendigo-sidrena/v1/cjenici/usluge/csv?oznaka=RJK",
            "xml":"https://sidrena.example.hr/wp-json/brendigo-sidrena/v1/cjenici/usluge/xml?oznaka=RJK"
          }
        }
      }]
    }
    """;
    var snapshot=OfficialPluginDiscovery.Parse(System.Text.Encoding.UTF8.GetBytes(restJson),
        OfficialPluginDiscovery.Endpoint("https://sidrena.example.hr"));
    Expect(snapshot.Feeds.Count==4 && snapshot.OutletCount==1,"Official REST v1 structure");
    Expect(snapshot.PublishedAt?.Year==2026,"Published time is parsed");
    Expect(snapshot.PublicPricelist.Host=="sidrena.example.hr","Same-origin public links");
    Expect(snapshot.ArchiveApi?.AbsolutePath.EndsWith("/wp-json/brendigo-sidrena/v1/arhiva")==true,
        "Official 1.5.0 exposes separate public archive REST API");
    Expect(snapshot.CombinedFeed?.AbsolutePath=="/sidrena-cijena.xml",
        "Official 1.5.0 combined public XML is recognized");
    Expect(snapshot.Outlets.Count==1 && snapshot.Outlets[0].Code=="RJK" &&
        snapshot.Outlets[0].Name=="Rijeka Centar" && snapshot.Outlets[0].Feeds.Count==4,
        "Official outlet feed metadata is mapped exactly");
    Expect(snapshot.Outlets[0].Feeds[0].Url.Query=="?oznaka=RJK",
        "Per-outlet public feed selection is preserved");
    var wrongOutlet=restJson.Replace("oznaka=RJK","oznaka=RJK%26x%3Devil");
    // Extra query parameters must not become unaudited write actions. These are only opened after confirmation.
    Expect(OfficialPluginDiscovery.Parse(System.Text.Encoding.UTF8.GetBytes(wrongOutlet),endpoint)
        .Outlets[0].Feeds.Count==4,"Read-only data stays passive");
    var unsafeOutlet=restJson.Replace("https://sidrena.example.hr/wp-json/brendigo-sidrena/v1/cjenici/proizvodi/csv?oznaka=RJK",
        "https://other-site.example.net/exfiltrate");
    MustThrowType<InvalidDataException>(()=>OfficialPluginDiscovery.Parse(
        System.Text.Encoding.UTF8.GetBytes(unsafeOutlet),endpoint));
    var duplicateCode=restJson.Replace("\"code\":\"RJK\"","\"code\":\"../unsafe\"");
    MustThrowType<InvalidDataException>(()=>OfficialPluginDiscovery.Parse(
        System.Text.Encoding.UTF8.GetBytes(duplicateCode),endpoint));
    var hostile=restJson.Replace("https://sidrena.example.hr/javni-cjenik","https://hostile.example.com/collection");
    MustThrowType<InvalidDataException>(()=>OfficialPluginDiscovery.Parse(
        System.Text.Encoding.UTF8.GetBytes(hostile),endpoint));
    using(var handler=new OfficialRestTestHandler(restJson))
    using(var client=new System.Net.Http.HttpClient(handler))
    {
        var fetched=OfficialPluginDiscovery.FetchAsync("https://sidrena.example.hr",client).GetAwaiter().GetResult();
        Expect(fetched.Feeds.Count==4 && handler.Requested?.AbsolutePath.EndsWith("/wp-json/brendigo-sidrena/v1/cjenici")==true,
            "HTTP request reads official public endpoint only");
        Expect(handler.Method==System.Net.Http.HttpMethod.Get,"Public API must use GET");
    }
    using(var handler=new OfficialRestTestHandler(restJson,System.Net.HttpStatusCode.Redirect))
    using(var client=new System.Net.Http.HttpClient(handler))
        MustThrowType<System.Net.Http.HttpRequestException>(()=>{
            OfficialPluginDiscovery.FetchAsync("https://sidrena.example.hr",client).GetAwaiter().GetResult();
        });
    // Pure-DIP desktop policy: window must not fill even a small work area.
    var largeWindow=ResponsiveLayoutPolicy.Initial(1920,1080);
    Expect(largeWindow.Width<=1320&&largeWindow.Height<=820&&
        largeWindow.Width<1920&&largeWindow.Height<1080,"Desktop starts windowed on full HD");
    var smallWindow=ResponsiveLayoutPolicy.Initial(800,600);
    Expect(smallWindow.Width<800&&smallWindow.Height<600&&smallWindow.Width>560,
        "Desktop fits smaller screens without fullscreen");
    var compact=ResponsiveLayoutPolicy.ForWidth(760);
    var normal=ResponsiveLayoutPolicy.ForWidth(1400);
    Expect(compact.CompactNavigation&&compact.SidebarWidth==75&&!compact.ShowHeroBadge,
        "Compact icon navigation avoids wasting screen width");
    Expect(!normal.CompactNavigation&&normal.ShowHeroBadge&&normal.SidebarWidth==246,
        "Full desktop navigation matches reference design");
    Expect(ResponsiveLayoutPolicy.StackDetails(640)&&
        !ResponsiveLayoutPolicy.StackDetails(1050),"Detail columns stack at compact widths");
    MustThrowType<ArgumentOutOfRangeException>(()=>ResponsiveLayoutPolicy.Initial(double.NaN,800));
    MustThrowType<ArgumentOutOfRangeException>(()=>ResponsiveLayoutPolicy.ForWidth(0));
    Console.WriteLine($"SIDRENA Desktop: {passed} provjera uspješno.");
}
finally
{
    try { Directory.Delete(folder, true); } catch (IOException) { }
}
void Expect(bool ok, string message)
{
    if (!ok) throw new Exception(message);
    passed++;
}
void MustThrow(Action action)
{
    try { action(); }
    catch (ArgumentException) { passed++; return; }
    throw new Exception("Missing validation");
}

void MustThrowType<T>(Action action) where T:Exception
{
    try { action(); }
    catch(T) {passed++;return;}
    throw new Exception("Expected "+typeof(T).Name);
}

sealed class OfficialRestTestHandler : System.Net.Http.HttpMessageHandler
{
    private readonly string _body;
    private readonly System.Net.HttpStatusCode _status;
    public Uri? Requested {get;private set;}
    public System.Net.Http.HttpMethod? Method {get;private set;}
    public OfficialRestTestHandler(string body,System.Net.HttpStatusCode status=System.Net.HttpStatusCode.OK)
    {
        _body=body;_status=status;
    }
    protected override Task<System.Net.Http.HttpResponseMessage> SendAsync(
        System.Net.Http.HttpRequestMessage request,CancellationToken cancellationToken)
    {
        Requested=request.RequestUri;Method=request.Method;
        return Task.FromResult(new System.Net.Http.HttpResponseMessage(_status) {
            Content=new System.Net.Http.StringContent(_body,System.Text.Encoding.UTF8,"application/json")
        });
    }
}
