package com.brendigo.sidrena.data

import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertThrows
import org.junit.Test

class CatalogDiscoveryTest {
    private val site = SiteUrl.parse("https://primjer.hr")
    private fun payload(): JSONObject = JSONObject(
        """
        {
          "api_version":1,
          "plugin_version":"1.5.2",
          "feeds":{
            "proizvodi":{"csv":"https://primjer.hr/wp-json/brendigo-sidrena/v1/cjenici/proizvodi/csv"},
            "usluge":{"csv":"https://primjer.hr/wp-json/brendigo-sidrena/v1/cjenici/usluge/csv"}
          },
          "outlets":[
            {"name":"Rijeka","code":"RI-01","feeds":{
              "proizvodi":{"csv":"https://primjer.hr/proizvodi.csv?oznaka=RI-01"},
              "usluge":{"csv":"https://primjer.hr/usluge.csv?oznaka=RI-01"}
            }}
          ]
        }
        """.trimIndent()
    )

    @Test fun validWordPressDiscoveryUsesPublicSameOrigin() {
        val index = CatalogDiscovery.parse(payload(), site)
        assertEquals("RI-01", index.outlets.single().code)
        assertEquals("1.5.2", index.pluginVersion)
        assertEquals("https://primjer.hr/usluge.csv?oznaka=RI-01", index.outlets.single().serviceCsv)
    }

    @Test fun supportsLegacyPluginWithoutOptionalLegalProfile() {
        val index = CatalogDiscovery.parse(payload().apply { remove("plugin_version") }, site)
        assertEquals(null, index.legal)
    }

    @Test fun rejectsUnknownOrMissingApiVersion() {
        for (value in listOf(0, 2, 99)) {
            assertThrows(IllegalArgumentException::class.java) {
                CatalogDiscovery.parse(payload().put("api_version", value), site)
            }
        }
        assertThrows(IllegalArgumentException::class.java) {
            CatalogDiscovery.parse(payload().apply { remove("api_version") }, site)
        }
    }

    @Test fun rejectsDuplicateOutletsCaseInsensitively() {
        val json = payload()
        val outlets = json.getJSONArray("outlets")
        val duplicated = JSONObject(outlets.getJSONObject(0).toString()).put("code", "ri-01")
        outlets.put(duplicated)
        assertThrows(IllegalArgumentException::class.java) {
            CatalogDiscovery.parse(json, site)
        }
    }

    @Test fun rejectsOversizedOutletsInsteadOfTruncating() {
        val json = payload().put("outlets", org.json.JSONArray().also { array ->
            repeat(501) { array.put(JSONObject()) }
        })
        assertThrows(IllegalArgumentException::class.java) {
            CatalogDiscovery.parse(json, site)
        }
    }

    @Test fun rejectsExternalLinksAndIncompleteFeeds() {
        assertThrows(IllegalArgumentException::class.java) {
            CatalogDiscovery.parse(payload().apply {
                getJSONObject("feeds").getJSONObject("usluge")
                    .put("csv", "https://nedozvoljeno.hr/feed.csv")
            }, site)
        }
        assertThrows(IllegalArgumentException::class.java) {
            CatalogDiscovery.parse(payload().apply {
                getJSONObject("feeds").remove("proizvodi")
            }, site)
        }
        assertThrows(IllegalArgumentException::class.java) {
            CatalogDiscovery.parse(payload().put("legal", "unknown"), site)
        }
    }

    @Test fun rejectsUnknownOutletCodeSyntax() {
        val json = payload()
        json.getJSONArray("outlets").getJSONObject(0).put("code", "../other")
        assertThrows(IllegalArgumentException::class.java) {
            CatalogDiscovery.parse(json, site)
        }
    }
}
