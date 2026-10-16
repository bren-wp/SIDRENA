using System.Globalization;
using System.Net;
using System.Text;

namespace Sidrena.Core;

/// <summary>
/// Local printable preview only, NOT a statutory PDF or web pricelist.
/// All database-derived text is HTML-escaped and the document has an
/// offline-only CSP. No JavaScript, network resources or web tracking.
/// </summary>
public static class LocalPricelistPreview
{
    private static readonly CultureInfo Hr=CultureInfo.GetCultureInfo("hr-HR");
    private static string Safe(string? value) => WebUtility.HtmlEncode(value ?? "");
    private static string Euro(long cents)=>(cents / 100m).ToString("N2",Hr)+" €";

    public static void Write(IEnumerable<PriceItem> items,string path,string title="SIDRENA — pregled cjenika")
    {
        ArgumentNullException.ThrowIfNull(items);
        ArgumentException.ThrowIfNullOrWhiteSpace(path);
        var entries=items.ToList();
        if(entries.Count>10000)throw new ArgumentException("Radni pregled ograničen je na 10.000 stavki.",nameof(items));
        string full=Path.GetFullPath(path);
        Directory.CreateDirectory(Path.GetDirectoryName(full)!);
        var html=new StringBuilder(4096+entries.Count*220);
        html.AppendLine("<!doctype html><html lang=\"hr\"><head><meta charset=\"utf-8\">");
        html.AppendLine("<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">");
        html.AppendLine("<meta http-equiv=\"Content-Security-Policy\" content=\"default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'\">");
        html.Append("<title>").Append(Safe(title)).AppendLine("</title>");
        html.AppendLine("""
          <style>
          :root{font-family:'Segoe UI',Arial,sans-serif;color:#162537;background:#e9eef5}
          *,*::before,*::after{box-sizing:border-box}
          body{max-width:1070px;margin:24px auto;padding:28px;background:#fff;box-shadow:0 4px 24px #14263d22;border-radius:14px}
          .brand{display:flex;align-items:center;gap:12px;color:#f97316;font-size:26px;font-weight:800;letter-spacing:1px}
          .brand small{color:#374151;font-size:13px;font-weight:400;letter-spacing:0}
          h1{margin:22px 0 8px} .subtitle{color:#64748b}
          .warning{border:2px solid #ef973a;background:#fff6e8;color:#7c3d0b;padding:13px 17px;border-radius:8px;margin:18px 0;font-weight:700}
          table{border-collapse:collapse;width:100%;font-size:13px}
          thead{background:#14263d;color:white;display:table-header-group}
          th,td{padding:11px 9px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top;overflow-wrap:anywhere}
          .money{text-align:right;white-space:nowrap}
          tbody tr:nth-child(even){background:#f8fafc}
          .muted{color:#64748b;font-size:11px}
          footer{margin-top:26px;color:#64748b;font-size:12px}
          @page{size:A4 portrait;margin:15mm}
          @media print{body{margin:0;max-width:none;padding:0;box-shadow:none;border-radius:0}
            .warning{-webkit-print-color-adjust:exact;print-color-adjust:exact}
            table{page-break-inside:auto} tr{break-inside:avoid} .money{white-space:nowrap}}
          </style></head><body>
          """);
        html.AppendLine("<header><div class=\"brand\">⚓ SIDRENA <small>Sidrene cijene i digitalni cjenici</small></div></header>");
        html.Append("<h1>").Append(Safe(title)).AppendLine("</h1>");
        html.Append("<p class=\"subtitle\">Izrađeno: ")
            .Append(Safe(DateTimeOffset.Now.ToString("dd. MM. yyyy. HH:mm",Hr)))
            .Append(" &nbsp; · &nbsp; Stavki: ").Append(entries.Count.ToString(Hr)).AppendLine("</p>");
        html.AppendLine("<div class=\"warning\">PRIPREMLJENI CJENIK — NIJE SLUŽBENI JAVNI CJENIK NI DOKAZ ZAKONSKE USKLAĐENOSTI</div>");
        html.AppendLine("<table><thead><tr><th>Naziv</th><th>Vrsta</th><th>Šifra</th><th>Jedinica</th><th class=\"money\">Aktualna cijena</th><th class=\"money\">Sidrena cijena</th><th>Referentni datum</th></tr></thead><tbody>");
        foreach(var item in entries)
        {
            html.Append("<tr><td>").Append(Safe(item.Name)).Append("</td><td>")
                .Append(Safe(item.Kind)).Append("</td><td>").Append(Safe(item.Sku))
                .Append("</td><td>").Append(Safe(item.Unit)).Append("</td><td class=\"money\">")
                .Append(Safe(Euro(item.CurrentCents))).Append("</td><td class=\"money\">")
                .Append(item.AnchorCents.HasValue?Safe(Euro(item.AnchorCents.Value)):"—")
                .Append("</td><td>").Append(Safe(item.AnchorDate ?? "—")).AppendLine("</td></tr>");
        }
        html.AppendLine("</tbody></table><footer>Dokument je namijenjen pregledu i ispisu. Sam po sebi ne potvrđuje da je cjenik zakonito javno objavljen ili usklađen s posebnim propisima.</footer></body></html>");
        string temp=full+"."+Guid.NewGuid().ToString("N")+".tmp";
        try
        {
            File.WriteAllText(temp,html.ToString(),new UTF8Encoding(false,true));
            File.Move(temp,full,true);
        }
        finally
        {
            if(File.Exists(temp))File.Delete(temp);
        }
    }
}
