package com.brendigo.sidrena.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.ByteArrayOutputStream
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URI
import java.nio.charset.StandardCharsets

/**
 * Uses only the current plugin's public v1 endpoints. No password, cookies or
 * unpublished WordPress/WooCommerce records are accessed.
 */
class CatalogRepository {
    suspend fun load(inputSite: String, selectedOutlet: String = ""): CatalogSnapshot = withContext(Dispatchers.IO) {
        val site = SiteUrl.parse(inputSite)
        val indexJson = JSONObject(read(site, site.endpoint("wp-json/brendigo-sidrena/v1/cjenici"), 256 * 1024))
        val mainFeeds = indexJson.getJSONObject("feeds")
        fun csvFrom(feeds: JSONObject, kind: String): String =
            feeds.optJSONObject(kind)?.optString("csv").orEmpty()

        val outlets = mutableListOf<Outlet>()
        val inputOutlets = indexJson.optJSONArray("outlets") ?: JSONArray()
        for (i in 0 until minOf(inputOutlets.length(), 200)) {
            val item = inputOutlets.optJSONObject(i) ?: continue
            val code = item.optString("code")
            if (code.isBlank()) continue
            val feeds = item.optJSONObject("feeds") ?: JSONObject()
            outlets.add(Outlet(item.optString("name", code), code,
                csvFrom(feeds, "proizvodi"), csvFrom(feeds, "usluge")))
        }
        val index = CatalogIndex(
            publishedAt = indexJson.optString("published_at").ifBlank { null },
            publicPage = site.publicLink(indexJson.optString("cjenik")),
            archivePage = site.publicLink(indexJson.optString("archive")),
            productsCsv = csvFrom(mainFeeds, "proizvodi"),
            servicesCsv = csvFrom(mainFeeds, "usluge"),
            outlets = outlets,
            pluginVersion = indexJson.optString("plugin_version").ifBlank { null },
            legal = LegalContract.parseOptional(indexJson.optJSONObject("legal")),
        )
        val selected = outlets.firstOrNull { it.code == selectedOutlet }
        require(selectedOutlet.isEmpty() || selected != null) { "Odabrana poslovnica više ne postoji." }
        val productUrl = selected?.productCsv ?: index.productsCsv
        val serviceUrl = selected?.serviceCsv ?: index.servicesCsv

        val warnings = mutableListOf<String>()
        if (index.legal == null) warnings.add("WordPress dodatak ne objavljuje pravni profil. Ažurirajte SIDRENA na 1.5.1.")
        fun safeItems(url: String, label: String): List<PriceItem> {
            if (url.isBlank()) {
                warnings.add("Nema objavljenog CSV cjenika: " + label + ".")
                return emptyList()
            }
            return try {
                CsvParser.items(read(site, site.approved(url), 6 * 1024 * 1024))
            } catch (exception: Exception) {
                warnings.add("Nije moguće učitati " + label + ". Provjerite objavu CSV cjenika.")
                emptyList()
            }
        }

        val products = safeItems(productUrl, "proizvode")
        val services = safeItems(serviceUrl, "usluge")
        val archive = try {
            val discoveryUrl = indexJson.optString("type_archive")
            val remote = if (discoveryUrl.isBlank())
                site.endpoint("wp-json/brendigo-sidrena/v1/arhiva")
            else site.approved(discoveryUrl)
            val list = JSONArray(read(site, remote, 512 * 1024))
            buildList {
                for (i in 0 until minOf(list.length(), 1000)) {
                    val item = list.optJSONObject(i) ?: continue
                    val outlet = item.optString("outlet")
                    if (selectedOutlet.isNotEmpty() && outlet != selectedOutlet) continue
                    if (selectedOutlet.isEmpty() && outlet.isNotBlank() && outlet != "webshop") continue
                    val url = site.publicLink(item.optString("url")) ?: continue
                    val format = item.optString("format")
                    if (format !in setOf("csv", "xml")) continue
                    add(ArchiveEntry(item.optString("date"), item.optString("kind"), outlet, format, url))
                }
            }
        } catch (exception: Exception) {
            warnings.add("Arhiva trenutačno nije dostupna.")
            emptyList()
        }

        CatalogSnapshot(site.display, index, products, services, archive, warnings)
    }

    private fun read(site: SiteUrl, initial: URI, maxBytes: Int): String {
        var url = site.approved(initial.toASCIIString())
        repeat(4) { redirect ->
            PublicNetworkGuard.verifyPublicDns(url.host)
            val connection = url.toURL().openConnection() as HttpURLConnection
            connection.instanceFollowRedirects = false
            connection.requestMethod = "GET"
            connection.connectTimeout = 8_000
            connection.readTimeout = 12_000
            connection.useCaches = false
            connection.setRequestProperty("Accept", "application/json, text/csv, */*")
            connection.setRequestProperty("User-Agent", "SIDRENA-Android/0.3.0")
            try {
                val code = connection.responseCode
                if (code in 300..399) {
                    if (redirect >= 3) throw IOException("Previše preusmjeravanja.")
                    val target = connection.getHeaderField("Location")
                        ?: throw IOException("Preusmjeravanje nema odredište.")
                    url = site.approved(url.resolve(target).toASCIIString())
                } else {
                    if (code !in 200..299) throw IOException("HTTP " + code)
                    val output = ByteArrayOutputStream()
                    connection.inputStream.use { stream ->
                        val buffer = ByteArray(8192)
                        while (true) {
                            val count = stream.read(buffer)
                            if (count < 0) break
                            if (output.size() + count > maxBytes) {
                                throw IOException("Datoteka prelazi sigurnosno ograničenje.")
                            }
                            output.write(buffer, 0, count)
                        }
                    }
                    return output.toString(StandardCharsets.UTF_8.name())
                }
            } finally {
                connection.disconnect()
            }
        }
        throw IOException("Previše preusmjeravanja.")
    }
}
