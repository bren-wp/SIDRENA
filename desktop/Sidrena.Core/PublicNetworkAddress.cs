using System.Net;
using System.Net.Sockets;

namespace Sidrena.Core;

/// <summary>
/// Blocks WordPress hostnames that resolve to loopback, private, link-local,
/// cloud-metadata and other non-public IP addresses. Apply at actual socket
/// connection time to resist DNS rebinding after hostname validation.
/// </summary>
public static class PublicNetworkAddress
{
    public static bool IsPublic(IPAddress address)
    {
        ArgumentNullException.ThrowIfNull(address);
        if(address.IsIPv4MappedToIPv6)address=address.MapToIPv4();
        var bytes=address.GetAddressBytes();
        if(bytes.Length==4)
        {
            int a=bytes[0],b=bytes[1];
            if(a==0||a==10||a==127||a>=224) return false;
            if(a==169&&b==254) return false;
            if(a==172&&b>=16&&b<=31) return false;
            if(a==192&&b==168) return false;
            if(a==100&&b>=64&&b<=127) return false;
            if(a==198&&(b==18||b==19)) return false;
            if(a==192&&b==0&&bytes[2]==0) return false;
            if(a==192&&b==0&&bytes[2]==2) return false;  // documentation
            if(a==198&&b==51&&bytes[2]==100) return false;
            if(a==203&&b==0&&bytes[2]==113) return false;
            return true;
        }
        if(bytes.Length!=16) return false;
        if(IPAddress.IsLoopback(address) || address.Equals(IPAddress.IPv6Any) ||
           address.Equals(IPAddress.IPv6None) || address.IsIPv6LinkLocal ||
           address.IsIPv6SiteLocal || address.IsIPv6Multicast)
            return false;
        if((bytes[0]&0xfe)==0xfc) return false; // fc00::/7
        if(bytes[0]==0x20&&bytes[1]==0x01&&bytes[2]==0x0d&&bytes[3]==0xb8)
            return false; // documentation addresses
        // Reject IPv4-compatible and unspecified IPv6 forms.
        if(bytes.Take(12).All(b=>b==0))return false;
        return true;
    }

    public static async ValueTask<Stream> ConnectAsync(
        SocketsHttpConnectionContext context,CancellationToken token)
    {
        var host=context.DnsEndPoint.Host;
        IPAddress[] resolved=await Dns.GetHostAddressesAsync(host,token).ConfigureAwait(false);
        foreach(var ip in resolved.Where(IsPublic))
        {
            var socket=new Socket(ip.AddressFamily,SocketType.Stream,ProtocolType.Tcp);
            try
            {
                await socket.ConnectAsync(new IPEndPoint(ip,context.DnsEndPoint.Port),token)
                    .ConfigureAwait(false);
                return new NetworkStream(socket,ownsSocket:true);
            }
            catch
            {
                socket.Dispose();
                throw;
            }
        }
        throw new HttpRequestException("WordPress adresa ne upućuje na dopušteno javno mrežno odredište.");
    }
}
