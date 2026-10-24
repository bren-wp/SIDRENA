package com.brendigo.sidrena.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertThrows
import org.junit.Test
import org.json.JSONObject

class LegalContractTest {
    @Test fun legalConstantsMatchCroatianRules() {
        assertEquals("2026-11-17", LegalContract.EFFECTIVE)
        assertEquals("2025-05-02", LegalContract.LEGACY)
        assertEquals("2026-09-10", LegalContract.GENERAL)
    }

    @Test fun oldPluginIsSupportedWithoutFabricatedLegalStatus() {
        assertNull(LegalContract.parseOptional(null))
    }

    @Test fun parseValidatedServerProfile() {
        val json=JSONObject("""{"schema":1,"jurisdiction":"HR",
          "rules_revision":"NN-101-2026-NN-110-2026",
          "effective_from":"2026-11-17",
          "reference_dates":{"legacy_fmcg":"2025-05-02",
             "general_goods_services":"2026-09-10"},
          "archive_min_days":30,"product_publication_deadline":"08:00"}""")
        val policy=LegalContract.parseOptional(json)
        assertEquals("2026-11-17", policy?.effectiveFrom)
        assertEquals(30, policy?.archiveMinDays)
        json.put("archive_min_days", 3)
        assertThrows(IllegalArgumentException::class.java) { LegalContract.parseOptional(json) }
    }
}
