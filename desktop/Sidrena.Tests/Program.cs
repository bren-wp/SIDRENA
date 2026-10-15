using Sidrena.Core;

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
        var csv = Path.Combine(folder, "RADNI.csv");
        var xml = Path.Combine(folder, "RADNI.xml");
        DraftExport.Csv(store.ListItems(), csv);
        DraftExport.Xml(store.ListItems(), xml);
        Expect(File.ReadAllText(csv).Contains("\"Mlijeko; \"\"punomasno\"\"\""), "CSV quoting");
        Expect(File.ReadAllText(xml).Contains("SidrenaRadniIzvoz"), "XML draft marker");
    }
    using (var reopened = new PriceStore(file))
    {
        Expect(reopened.ListItems().Count == 2, "Durable database");
        Expect(reopened.ListChanges(id).Count == 1, "Durable audit trail");
    }
    Console.WriteLine("SIDRENA Desktop: 10 provjera uspješno.");
}
finally
{
    try { Directory.Delete(folder, true); } catch (IOException) { }
}
static void Expect(bool ok, string message) { if (!ok) throw new Exception(message); }
static void MustThrow(Action action)
{
    try { action(); } catch (ArgumentException) { return; }
    throw new Exception("Missing validation");
}
