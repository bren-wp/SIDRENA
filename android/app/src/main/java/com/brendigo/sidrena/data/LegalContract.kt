package com.brendigo.sidrena.data

import org.json.JSONObject
import java.time.LocalDate
import java.time.format.DateTimeParseException

/** The public WordPress legal profile is advisory; no inferred historical prices. */
data class LegalContract(
    val rulesRevision: String,
    val effectiveFrom: String,
    val legacyReferenceDate: String,
    val generalReferenceDate: String,
    val archiveMinDays: Int,
    val dailyDeadline: String,
    val consumerLawRevision: String? = null,
    val unitPriceRuleRevision: String? = null,
) {
    companion object {
        const val REVISION = "NN-101-2026-NN-110-2026"
        const val EFFECTIVE = "2026-11-17"
        const val LEGACY = "2025-05-02"
        const val GENERAL = "2026-09-10"
        const val CONSUMER_LAW = "NN-59-2026"
        const val UNIT_PRICE_RULE = "NN-105-2026"

        fun parseOptional(json: JSONObject?): LegalContract? {
            if (json == null) return null // Old WordPress API version.
            require(json.optInt("schema", -1) == 1 && json.optString("jurisdiction") == "HR") {
                "Nepodržani pravni profil."
            }
            val revision = json.getString("rules_revision")
            require(revision.length in 5..80) { "Neispravno pravno izdanje." }
            val effective = iso(json.getString("effective_from"))
            val dates = json.getJSONObject("reference_dates")
            val legacy = iso(dates.getString("legacy_fmcg"))
            val general = iso(dates.getString("general_goods_services"))
            val days = json.getInt("archive_min_days")
            require(days in 30..3650) { "Neispravno čuvanje arhive." }
            val deadline = json.getString("product_publication_deadline")
            require(Regex("(?:[01][0-9]|2[0-3]):[0-5][0-9]").matches(deadline)) {
                "Neispravno vrijeme objave."
            }
            fun optionalRevision(field: String): String? {
                if (!json.has(field) || json.isNull(field)) return null
                val value = json.getString(field)
                require(Regex("NN-[0-9]{1,3}-[0-9]{4}").matches(value)) {
                    "Neispravna oznaka pravnog izvora."
                }
                return value
            }
            return LegalContract(
                revision, effective, legacy, general, days, deadline,
                optionalRevision("consumer_law_revision"),
                optionalRevision("unit_price_rule_revision"),
            )
        }

        private fun iso(value: String): String {
            try {
                require(LocalDate.parse(value).toString() == value) { "Neispravan datum." }
                return value
            } catch (_: DateTimeParseException) {
                throw IllegalArgumentException("Neispravan datum u pravnom profilu.")
            }
        }
    }
}
