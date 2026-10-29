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
        assertEquals("2026-09-26", LegalContract.UNIT_PRICE_EFFECTIVE)
    }

    @Test fun oldPluginIsSupportedWithoutFabricatedLegalStatus() {
        assertNull(LegalContract.parseOptional(null))
    }

    @Test fun parseValidatedServerProfile() {
        val json=JSONObject("""{"schema":1,"jurisdiction":"HR",
          "rules_revision":"NN-101-2026-NN-110-2026",
          "consumer_law_revision":"NN-59-2026",
          "unit_price_rule_revision":"NN-105-2026",
          "price_reduction_lookback_days":30,
          "discount_baseline_is_anchor_price":false,
          "unit_price_rule_effective_from":"2026-09-26",
          "effective_from":"2026-11-17",
          "reference_dates":{"legacy_fmcg":"2025-05-02",
             "general_goods_services":"2026-09-10"},
          "archive_min_days":30,"product_publication_deadline":"08:00"}""")
        val policy=LegalContract.parseOptional(json)
        assertEquals("2026-11-17", policy?.effectiveFrom)
        assertEquals(30, policy?.archiveMinDays)
        assertEquals("NN-59-2026", policy?.consumerLawRevision)
        assertEquals("NN-105-2026", policy?.unitPriceRuleRevision)
        assertEquals(30, policy?.priceReductionLookbackDays)
        assertEquals(false, policy?.discountBaselineIsAnchorPrice)
        assertEquals("2026-09-26", policy?.unitPriceRuleEffectiveFrom)
        assertThrows(IllegalArgumentException::class.java) {
            LegalContract.parseOptional(JSONObject(json.toString()).put("discount_baseline_is_anchor_price", true))
        }
        assertThrows(IllegalArgumentException::class.java) {
            LegalContract.parseOptional(JSONObject(json.toString()).put("price_reduction_lookback_days", 5))
        }
        val invalidRevision = JSONObject(json.toString()).put("unit_price_rule_revision", "script:value")
        assertThrows(IllegalArgumentException::class.java) { LegalContract.parseOptional(invalidRevision) }
        json.put("archive_min_days", 3)
        assertThrows(IllegalArgumentException::class.java) { LegalContract.parseOptional(json) }
    }
}
