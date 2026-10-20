package com.brendigo.sidrena.data

/** Filtering uses only data returned by the public SIDRENA API. */
object CatalogSearch {
    fun items(items: List<PriceItem>, query: String, anchor: Int = 0): List<PriceItem> {
        require(anchor in 0..2)
        val terms = query.trim().take(256).split(Regex("\\s+")).filter { it.isNotEmpty() }
        return items.filter { item ->
            val hasAnchor = item.referencePrice.isNotBlank()
            (anchor == 0 || (anchor == 1) == hasAnchor) &&
                terms.all { term ->
                    listOf(item.name, item.id, item.sku, item.kind, item.availability)
                        .any { field -> field.contains(term, ignoreCase = true) }
                }
        }
    }

    fun archives(items: List<ArchiveEntry>, query: String, format: String = "sve"): List<ArchiveEntry> {
        return items.filter {
            (format == "sve" || it.format.equals(format, ignoreCase = true)) &&
                (it.date + " " + it.kind + " " + it.outlet + " " + it.format)
                    .contains(query.trim(), ignoreCase = true)
        }
    }
}
