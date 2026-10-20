package com.brendigo.sidrena.data

/**
 * RFC 4180-style quoted fields, with SIDRENA's semicolon separator.
 * Rejects unclosed fields and excessive row counts instead of silently truncating.
 */
object CsvParser {
    fun parse(input: String, maxRows: Int = 10_001): List<List<String>> {
        require(maxRows > 0)
        val text = input.removePrefix("\uFEFF")
        val result = mutableListOf<List<String>>()
        var row = mutableListOf<String>()
        val field = StringBuilder()
        var quoted = false
        var closedQuote = false
        var atFieldStart = true
        var index = 0
        fun pushField() {
            row.add(field.toString())
            field.setLength(0)
            closedQuote = false
            atFieldStart = true
        }
        fun pushRow() {
            pushField()
            result.add(row)
            require(result.size <= maxRows) { "Cjenik sadrži previše redaka." }
            row = mutableListOf()
        }
        while (index < text.length) {
            val c = text[index]
            if (quoted) {
                if (c == '"') {
                    if (index + 1 < text.length && text[index + 1] == '"') {
                        field.append('"')
                        index++
                    } else {
                        quoted = false
                        closedQuote = true
                    }
                } else {
                    field.append(c)
                }
            } else {
                when (c) {
                    '"' -> {
                        require(atFieldStart) { "Neispravan CSV navodnik." }
                        quoted = true
                        atFieldStart = false
                    }
                    ';' -> pushField()
                    '\n', '\r' -> {
                        pushRow()
                        if (c == '\r' && index + 1 < text.length && text[index + 1] == '\n') index++
                    }
                    else -> {
                        require(!closedQuote) { "Neispravan znak nakon CSV navodnika." }
                        field.append(c)
                        atFieldStart = false
                    }
                }
            }
            index++
        }
        require(!quoted) { "CSV ima nezatvorene navodnike." }
        if (field.isNotEmpty() || row.isNotEmpty() || closedQuote || !atFieldStart) pushRow()
        return result
    }

    fun items(csv: String): List<PriceItem> {
        val lines = parse(csv)
        if (lines.isEmpty()) return emptyList()
        val columns = lines.first().map { it.trim().lowercase() }
        require(columns.contains("naziv") && columns.contains("maloprodajna_cijena")) {
            "Datoteka nije SIDRENA javni CSV cjenik."
        }
        return lines.drop(1).filter { row -> row.any { it.isNotBlank() } }.map { row ->
            fun value(column: String): String = columns.indexOf(column)
                .takeIf { it >= 0 }
                ?.let { row.getOrNull(it) }
                ?.trim()
                .orEmpty()
            PriceItem(
                id = value("id"),
                name = value("naziv"),
                kind = value("vrsta"),
                currentPrice = value("maloprodajna_cijena"),
                referencePrice = value("sidrena_cijena"),
                referenceDate = value("sidrena_datum"),
                sku = value("sku"),
                availability = value("dostupnost"),
                unit = value("jedinica_mjere"),
            )
        }
    }
}
