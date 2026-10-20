package com.brendigo.sidrena.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertThrows
import org.junit.Test

class SiteUrlTest {
    @Test fun supportsWordPressSubdirectories() {
        val site = SiteUrl.parse("https://primjer.hr/trgovina")
        assertEquals("https://primjer.hr:443/trgovina/wp-json/brendigo-sidrena/v1/cjenici",
            site.endpoint("wp-json/brendigo-sidrena/v1/cjenici").toASCIIString())
    }

    @Test fun rejectsPlainHttpAndLocalHosts() {
        for (url in listOf("http://primjer.hr", "https://localhost", "https://127.0.0.1",
            "https://admin:lozinka@primjer.hr", "https://primjer.hr/a/../wp")) {
            assertThrows(url) { SiteUrl.parse(url) }
        }
    }

    @Test fun disallowsCrossDomainFeeds() {
        val site = SiteUrl.parse("https://primjer.hr")
        assertThrows(IllegalArgumentException::class.java) {
            site.approved("https://nepoznato.hr/wp-content/uploads/feed.csv")
        }
        assertEquals(null, site.publicLink("http://primjer.hr/cjenik"))
    }

    private fun assertThrows(url: String, block: () -> Unit) {
        assertThrows(IllegalArgumentException::class.java) { block() }
    }
}
