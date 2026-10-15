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
        var csv = Path.Combine(folder, "RADNI.csv");
        workspace.RecordDraftExport(csv, "CSV", 2);
        var drafts = workspace.ListDraftExports();
        Expect(drafts.Count == 1 && drafts[0].Sha256.Length == 64, "SHA256 draft record");
        MustThrow(() => workspace.RecordDraftExport(csv, "PDF", 2));
        var backup = Path.Combine(folder, "backup.db");
        workspace.BackupTo(backup);
        using var backupPrices = new PriceStore(backup);
        Expect(backupPrices.ListItems().Count == 2, "Online backup includes WAL data");
        using var backupWorkspace = new WorkspaceStore(backup);
        Expect(backupWorkspace.ListLocations().Count == 1, "Online backup includes workspace");
        MustThrow(() => workspace.BackupTo(file));
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
    }
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
