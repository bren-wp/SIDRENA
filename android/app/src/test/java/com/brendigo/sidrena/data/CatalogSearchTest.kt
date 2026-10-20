package com.brendigo.sidrena.data

import org.junit.Assert.assertEquals
import org.junit.Test

class CatalogSearchTest {
    private val items = listOf(
        PriceItem("1", "Trajno mlijeko", "proizvod", "1,09", "1,29", "2026-01-01", "SKU-1", "Dostupno", "L"),
        PriceItem("2", "Kruh", "proizvod", "1,49", "", "", "SKU-2", "Dostupno", "kom"),
    )

    @Test fun allSearchTermsMatchAcrossNameAndSku() {
        assertEquals(1, CatalogSearch.items(items, "mlijeko sku-1").size)
        assertEquals(0, CatalogSearch.items(items, "mlijeko kruh").size)
    }

    @Test fun referencePriceFiltersDoNotFabricateAnchors() {
        assertEquals("1", CatalogSearch.items(items, "", 1).single().id)
        assertEquals("2", CatalogSearch.items(items, "", 2).single().id)
    }

    @Test fun archivesFilterFormat() {
        val archive = listOf(
            ArchiveEntry("2026-01-01", "proizvodi", "webshop", "csv", "https://primjer.hr/a.csv"),
            ArchiveEntry("2026-01-01", "proizvodi", "webshop", "xml", "https://primjer.hr/a.xml"),
        )
        assertEquals("csv", CatalogSearch.archives(archive, "", "csv").single().format)
        assertEquals(2, CatalogSearch.archives(archive, "").size)
    }
}
