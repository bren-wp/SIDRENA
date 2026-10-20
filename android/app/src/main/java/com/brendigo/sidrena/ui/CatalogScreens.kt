package com.brendigo.sidrena.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ArrowBack
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.History
import androidx.compose.material.icons.outlined.Inventory2
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.brendigo.sidrena.data.CatalogSearch
import com.brendigo.sidrena.data.CatalogSnapshot
import com.brendigo.sidrena.data.PriceItem

@Composable
fun CatalogPage(
    data: CatalogSnapshot,
    isService: Boolean,
    onDetails: (PriceItem) -> Unit,
    onSearch: () -> Unit,
    onSwitchKind: () -> Unit,
) {
    val source = if (isService) data.services else data.products
    var query by rememberSaveable(isService) { mutableStateOf("") }
    var filter by rememberSaveable(isService) { mutableIntStateOf(0) }
    val list = CatalogSearch.items(source, query, filter)
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        item {
            PageHeading(
                if (isService) "Usluge" else "Proizvodi",
                if (isService) "Pregledajte objavljene usluge i njihove cijene."
                else "Pregledajte proizvode, cijene i dostupnost.",
            )
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                AppIcon(if (isService) Icons.Outlined.Settings else Icons.Outlined.Inventory2,
                    if (isService) SoftBlue else Accent)
                Spacer(Modifier.width(12.dp))
                Text(source.size.toString(), style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold)
                Spacer(Modifier.width(8.dp))
                MetaText(if (isService) "Ukupno usluga" else "Ukupno proizvoda")
            }
            Spacer(Modifier.height(15.dp))
            OutlinedTextField(
                value = query,
                onValueChange = { query = it },
                modifier = Modifier.fillMaxWidth(),
                leadingIcon = { Icon(Icons.Outlined.Search, null) },
                trailingIcon = { TextButton(onClick = onSearch) { Text("Sve", color = SoftBlue) } },
                singleLine = true,
                placeholder = { Text(if (isService) "Pretražite usluge" else "Pretraži proizvode") },
            )
            Spacer(Modifier.height(9.dp))
            Row(
                Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                listOf("Svi", "Sa sidrenom cijenom", "Bez sidrene cijene")
                    .forEachIndexed { index, title ->
                        FilterChip(
                            selected = filter == index,
                            onClick = { filter = index },
                            label = { Text(title) },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = Accent, selectedLabelColor = Background),
                        )
                    }
            }
            TextButton(onClick = onSwitchKind) {
                Text(if (isService) "Prikaži proizvode" else "Prikaži usluge", color = SoftBlue)
            }
            MetaText("Prikazano " + list.size + " od " + source.size + " stavki")
            Spacer(Modifier.height(4.dp))
        }
        if (list.isEmpty()) {
            item { Panel { MetaText("Nema stavki koje odgovaraju odabranim filtrima.") } }
        } else {
            items(list) { item ->
                CatalogItemCard(item, isService, onClick = { onDetails(item) })
            }
        }
        item { Spacer(Modifier.height(16.dp)) }
    }
}

@Composable
private fun CatalogItemCard(item: PriceItem, service: Boolean, onClick: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick),
        shape = RoundedCornerShape(17.dp),
        border = BorderStroke(1.dp, Border),
        colors = CardDefaults.cardColors(containerColor = Surface),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(14.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            AppIcon(
                if (service) Icons.Outlined.Settings else Icons.Outlined.Inventory2,
                if (service) SoftBlue else Accent, 54)
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(5.dp)) {
                Text(item.name.ifBlank { "Bez naziva" },
                    style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold,
                    maxLines = 2, overflow = TextOverflow.Ellipsis)
                if (item.sku.isNotBlank()) MetaText("SKU: " + item.sku)
                if (item.availability.isNotBlank()) {
                    Pill(item.availability, good = !item.availability.contains("nedost", true))
                }
                Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Column(Modifier.weight(1f)) {
                        MetaText("Trenutačna cijena")
                        Text(formatPrice(item.currentPrice), color = Accent, fontWeight = FontWeight.Bold)
                    }
                    Column(Modifier.weight(1f)) {
                        MetaText("Sidrena cijena")
                        Text(formatPrice(item.referencePrice), fontWeight = FontWeight.Bold)
                    }
                }
                if (item.referenceDate.isNotBlank()) {
                    MetaText("Datum sidrene cijene: " + item.referenceDate)
                }
            }
            Icon(Icons.Outlined.ChevronRight, contentDescription = "Detalji",
                tint = TextMuted, modifier = Modifier.size(22.dp))
        }
    }
}

fun formatPrice(raw: String): String {
    if (raw.isBlank()) return "—"
    return raw.trim() + " €"
}

@Composable
fun DetailPage(
    item: PriceItem?,
    data: CatalogSnapshot,
    onBack: () -> Unit,
    onOpen: (String) -> Unit,
) {
    if (item == null) {
        Column { PageHeading("Detalj stavke"); OutlineAction("Povratak", onBack) }
        return
    }
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            TextButton(onClick = onBack) {
                Icon(Icons.Outlined.ArrowBack, contentDescription = null, tint = SoftBlue)
                Spacer(Modifier.width(8.dp))
                Text("Detalj stavke", color = Color.White,
                    style = MaterialTheme.typography.titleLarge)
            }
        }
        item {
            Panel {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        AppIcon(if (item.kind.contains("uslug", true)) Icons.Outlined.Settings
                            else Icons.Outlined.Inventory2, Accent, 72)
                        Spacer(Modifier.width(14.dp))
                        Column(Modifier.weight(1f)) {
                            Text(item.name, style = MaterialTheme.typography.titleLarge,
                                fontWeight = FontWeight.Bold)
                            MetaText("Objavljena stavka")
                        }
                    }
                    Separator()
                    DetailRow("Vrsta", item.kind.ifBlank { "—" })
                    DetailRow("SKU", item.sku.ifBlank { "—" })
                    DetailRow("Trenutačna cijena", formatPrice(item.currentPrice))
                    DetailRow("Sidrena cijena", formatPrice(item.referencePrice))
                    DetailRow("Datum sidrene cijene", item.referenceDate.ifBlank { "—" })
                    DetailRow("Dostupnost", item.availability.ifBlank { "—" })
                    DetailRow("Jedinica mjere", item.unit.ifBlank { "—" })
                }
            }
        }
        item {
            Panel {
                Column(verticalArrangement = Arrangement.spacedBy(9.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        AppIcon(Icons.Outlined.History, SoftBlue)
                        Spacer(Modifier.width(10.dp))
                        Text("Povijest promjena", fontWeight = FontWeight.Bold)
                    }
                    MetaText("Pojedinačni zapisi promjena nisu dostupni kroz javno sučelje povezane stranice.")
                    if (item.referenceDate.isNotBlank() && item.referencePrice.isNotBlank()) {
                        DetailRow("Zabilježena sidrena cijena", formatPrice(item.referencePrice))
                        DetailRow("Datum", item.referenceDate)
                    }
                }
            }
        }
        item {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlineAction("Povratak", onBack, Modifier.weight(1f))
                data.index.publicPage?.let { url ->
                    AccentButton("Javni cjenik", { onOpen(url) }, Modifier.weight(1f))
                }
            }
            Spacer(Modifier.height(16.dp))
        }
    }
}

@Composable
private fun DetailRow(title: String, value: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp),
        verticalAlignment = Alignment.CenterVertically) {
        Text(title, modifier = Modifier.weight(1f),
            color = TextMuted, style = MaterialTheme.typography.bodyMedium)
        Text(value, modifier = Modifier.weight(1f), fontWeight = FontWeight.SemiBold,
            style = MaterialTheme.typography.bodyMedium)
    }
    Separator()
}
