package com.brendigo.sidrena.data

import java.text.NumberFormat
import java.util.Locale

/** Presents numeric WordPress price fields according to Croatian currency conventions. */
object PriceDisplay {
    fun euro(raw: String): String {
        val cleaned = raw.trim().removeSuffix("€").trim()
        if (cleaned.isEmpty()) return "—"
        val compact = cleaned.replace(" ", "").replace("\u00A0", "")
        val numberText = if (compact.contains(',') && compact.contains('.'))
            compact.replace(".", "").replace(',', '.')
        else compact.replace(',', '.')
        val number = numberText.toBigDecimalOrNull()
            ?: return cleaned + " €"
        return NumberFormat.getCurrencyInstance(Locale.forLanguageTag("hr-HR")).format(number)
    }
}
