package com.brendigo.sidrena.data

import java.net.InetAddress

/** Defense-in-depth DNS preflight for user-entered public WordPress sites.
 * This cannot bind an Android HttpURLConnection to a verified resolved IP;
 * never use it as a sole protection against DNS rebinding. */
object PublicNetworkGuard {
    fun verifyPublicDns(host: String) {
        val addresses = InetAddress.getAllByName(host)
        require(addresses.isNotEmpty() && addresses.all(::isPublic)) {
            "WordPress stranica mora biti dostupna na javnoj mrežnoj adresi."
        }
    }

    fun isPublic(ip: InetAddress): Boolean {
        if (ip.isAnyLocalAddress || ip.isLoopbackAddress || ip.isLinkLocalAddress ||
            ip.isSiteLocalAddress || ip.isMulticastAddress) return false
        val b = ip.address.map { it.toInt() and 255 }
        if (b.size == 4) {
            val a = b[0]
            val second = b[1]
            if (a == 0 || a == 10 || a == 127 || a >= 224 ||
                (a == 100 && second in 64..127) ||
                (a == 169 && second == 254) ||
                (a == 172 && second in 16..31) ||
                (a == 192 && second == 168) ||
                (a == 192 && second == 0 && b[2] == 0) ||
                (a == 192 && second == 0 && b[2] == 2) ||
                (a == 198 && second in 18..19) ||
                (a == 198 && second == 51 && b[2] == 100) ||
                (a == 203 && second == 0 && b[2] == 113)) return false
            return true
        }
        if (b.size == 16) {
            // ULA, IPv6 link-local, documentation range and unspecified.
            if ((b[0] and 0xfe) == 0xfc || (b[0] == 0xfe && (b[1] and 0xc0) == 0x80) ||
                (b[0] == 0x20 && b[1] == 0x01 && b[2] == 0x0d && b[3] == 0xb8)) return false
            // IPv4-mapped addresses must meet the IPv4 policy.
            if (b.take(10).all { it == 0 } && b[10] == 255 && b[11] == 255)
                return isPublic(InetAddress.getByAddress(b.drop(12).map { it.toByte() }.toByteArray()))
            return true
        }
        return false
    }
}
