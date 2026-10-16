using System.Globalization;
using System.Net;
using System.Net.Http;
using System.Text.Json;

namespace Sidrena.Core;

public sealed record OfficialFeed(string Kind,string Format,Uri Url);
public sealed record OfficialPluginSnapshot(DateTimeOffset? PublishedAt,Uri PublicPricelist,Uri PublicArchive,
    IReadOnlyList<OfficialFeed> Feeds,int OutletCount);

/// <summary>Read-only official plugin API. Does not send prices, credentials or local database contents.</summary>
public static class OfficialPluginDiscovery
{
    public static Uri Endpoint(string site)
    {
        if(!Uri.TryCreate(site?.Trim(),UriKind.Absolute,out var uri) ||
           uri.Scheme!=Uri.UriSchemeHttps || !string.IsNullOrEmpty(uri.UserInfo) ||
           !string.IsNullOrEmpty(uri.Query) || !string.IsNullOrEmpty(uri.Fragment) ||
           uri.HostNameType!=UriHostNameType.Dns ||
           uri.Host.Equals("localhost",StringComparison.OrdinalIgnoreCase) ||
           uri.Host.EndsWith(".local",StringComparison.OrdinalIgnoreCase))
            throw new ArgumentException("Unesite javnu WordPress HTTPS adresu bez korisničkih podataka i parametara.",nameof(site));
        string path=uri.AbsolutePath.TrimEnd('/')+"/";
        var siteRoot=new UriBuilder(uri){Path=path,Query="",Fragment=""}.Uri;
        return new Uri(siteRoot,"wp-json/brendigo-sidrena/v1/cjenici");
    }

    public static async Task<OfficialPluginSnapshot> FetchAsync(
        string site,HttpClient? client=null,CancellationToken token=default)
    {
        var endpoint=Endpoint(site);
        if(client is null)
        {
            using var owned=new HttpClient(new HttpClientHandler {AllowAutoRedirect=false,AutomaticDecompression=
                DecompressionMethods.GZip|DecompressionMethods.Deflate}){Timeout=TimeSpan.FromSeconds(12)};
            return await FetchAsync(endpoint,owned,token).ConfigureAwait(false);
        }
        return await FetchAsync(endpoint,client,token).ConfigureAwait(false);
    }

    private static async Task<OfficialPluginSnapshot> FetchAsync(Uri endpoint,HttpClient client,CancellationToken token)
    {
        using var request=new HttpRequestMessage(HttpMethod.Get,endpoint);
        request.Headers.Accept.ParseAdd("application/json");
        using var response=await client.SendAsync(request,HttpCompletionOption.ResponseHeadersRead,token).ConfigureAwait(false);
        if((int)response.StatusCode is >=300 and <400)
            throw new HttpRequestException("WordPress API preusmjerava na drugu adresu; provjerite HTTPS i adresu weba.");
        response.EnsureSuccessStatusCode();
        if(response.Content.Headers.ContentLength is >131072)
            throw new InvalidDataException("Odgovor WordPress API-ja je prevelik.");
        await using var stream=await response.Content.ReadAsStreamAsync(token).ConfigureAwait(false);
        using var storage=new MemoryStream();
        byte[] buffer=new byte[8192];
        while(true)
        {
            int read=await stream.ReadAsync(buffer,token).ConfigureAwait(false);
            if(read==0)break;
            if(storage.Length+read>131072)throw new InvalidDataException("WordPress odgovor prelazi dopuštenu veličinu.");
            storage.Write(buffer,0,read);
        }
        return Parse(storage.ToArray(),endpoint);
    }

    public static OfficialPluginSnapshot Parse(byte[] json,Uri endpoint)
    {
        ArgumentNullException.ThrowIfNull(json);
        ArgumentNullException.ThrowIfNull(endpoint);
        if(json.Length>131072)throw new InvalidDataException("API odgovor je prevelik.");
        using var document=JsonDocument.Parse(json);
        var root=document.RootElement;
        if(root.ValueKind!=JsonValueKind.Object ||
           !root.TryGetProperty("feeds",out var feeds) || feeds.ValueKind!=JsonValueKind.Object)
            throw new InvalidDataException("Ovo nije odgovor službenog Brendigo Sidrena Cijena REST API-ja.");

        Uri Url(string field)
        {
            if(!root.TryGetProperty(field,out var value) || value.ValueKind!=JsonValueKind.String)
                throw new InvalidDataException("Nedostaje javni WordPress podatak: "+field);
            return SecureUrl(value.GetString(),endpoint);
        }
        var cjenik=Url("cjenik");
        var archive=Url("archive");
        DateTimeOffset? published=null;
        if(root.TryGetProperty("published_at",out var p) && p.ValueKind==JsonValueKind.String)
        {
            if(!DateTimeOffset.TryParse(p.GetString(),CultureInfo.InvariantCulture,DateTimeStyles.RoundtripKind,out var parsed))
                throw new InvalidDataException("Datum objave u odgovoru nije valjan.");
            published=parsed;
        }
        var list=new List<OfficialFeed>();
        foreach(var kind in new[]{"proizvodi","usluge"})
        {
            if(!feeds.TryGetProperty(kind,out var type) || type.ValueKind!=JsonValueKind.Object)
                throw new InvalidDataException("Nedostaju javni podaci za "+kind);
            foreach(var format in new[]{"csv","xml"})
            {
                if(!type.TryGetProperty(format,out var value)||value.ValueKind!=JsonValueKind.String)
                    throw new InvalidDataException("Nedostaje format: "+kind+"/"+format);
                list.Add(new OfficialFeed(kind,format,SecureUrl(value.GetString(),endpoint)));
            }
        }
        int outlets=0;
        if(root.TryGetProperty("outlets",out var branch))
        {
            if(branch.ValueKind!=JsonValueKind.Array)
                throw new InvalidDataException("Popis poslovnica nije valjan.");
            outlets=branch.GetArrayLength();
            if(outlets>500)throw new InvalidDataException("Neočekivano velik broj poslovnica.");
        }
        return new OfficialPluginSnapshot(published,cjenik,archive,list,outlets);
    }

    private static Uri SecureUrl(string? value,Uri origin)
    {
        if(!Uri.TryCreate(value,UriKind.Absolute,out var url) ||
           url.Scheme!=Uri.UriSchemeHttps ||
           !url.IdnHost.Equals(origin.IdnHost,StringComparison.OrdinalIgnoreCase) ||
           url.Port!=origin.Port || !string.IsNullOrEmpty(url.UserInfo))
            throw new InvalidDataException("API je vratio neprovjerenu ili nesigurnu vanjsku adresu.");
        return url;
    }
}
