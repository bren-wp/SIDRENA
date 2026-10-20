package com.brendigo.sidrena.data

import java.net.URI

/** Conservative public WordPress same-origin URL guard. */
class SiteUrl private constructor(val base: URI) {
    val display: String get() = base.toASCIIString().removeSuffix("/")

    companion object {
        fun parse(value: String): SiteUrl {
            val uri = try { URI(value.trim()) } catch (_: Exception) {
                throw IllegalArgumentException("Unesite valjanu HTTPS adresu WordPress stranice.")
            }
            val host = uri.host?.lowercase().orEmpty()
            val labels = host.split('.')
            require(
                uri.scheme.equals("https", true) &&
                    uri.rawUserInfo == null && uri.rawQuery == null && uri.rawFragment == null &&
                    (uri.port == -1 || uri.port == 443) &&
                    labels.size >= 2 &&
                    labels.all { it.isNotBlank() && it.length <= 63 &&
                        it.matches(Regex("[a-z0-9-]+")) && !it.startsWith('-') && !it.endsWith('-') } &&
                    !host.matches(Regex("[0-9.]+")) &&
                    host.length <= 253 &&
                    host !in setOf("localhost", "localhost.localdomain") &&
                    !host.endsWith(".local") &&
                    !host.endsWith(".internal") &&
                    !host.endsWith(".test") &&
                    !host.endsWith(".localhost")
            ) { "Potrebna je javna HTTPS domena bez korisničkih podataka ili parametara." }
            val originalPath = uri.rawPath.orEmpty()
            require(!originalPath.contains("//") && !originalPath.contains("\\") &&
                originalPath.split('/').none { it == "." || it == ".." || it.contains('%') }) {
                "Putanja web-stranice nije podržana."
            }
            val path = if (originalPath.isBlank()) "/" else originalPath.trimEnd('/') + "/"
            val normalized = URI("https", null, host, 443, path, null, null)
            return SiteUrl(normalized)
        }
    }

    fun endpoint(path: String): URI {
        require(!path.startsWith('/') && !path.contains("..") && !path.contains("://"))
        return base.resolve(path)
    }

    fun approved(remote: String): URI {
        val url = try { URI(remote) } catch (_: Exception) {
            throw IllegalArgumentException("Poslužitelj je vratio neispravnu adresu.")
        }
        require(
            url.scheme.equals("https", true) &&
                url.host.equals(base.host, true) &&
                (url.port == -1 || url.port == 443) &&
                url.rawUserInfo == null && url.rawFragment == null &&
                !url.rawPath.orEmpty().contains("\\") &&
                !url.rawPath.orEmpty().contains("%")
        ) { "Poveznica nije s iste HTTPS domene." }
        return url
    }

    fun publicLink(remote: String?): String? =
        remote?.takeIf { it.isNotBlank() }?.let { runCatching { approved(it).toASCIIString() }.getOrNull() }
}
