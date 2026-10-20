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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Archive
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Inventory2
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material.icons.outlined.Store
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
import androidx.compose.ui.text.font.FontWeight
import com.brendigo.sidrena.data.ArchiveEntry
import com.brendigo.sidrena.data.CatalogSearch
import com.brendigo.sidrena.data.CatalogSnapshot
import com.brendigo.sidrena.data.PriceItem
import androidx.compose.ui.unit.dp

@Composable
fun ArchivePage(data: CatalogSnapshot, onOpen: (String) -> Unit) {
    var format by rememberSaveable { mutableStateOf("sve") }
    var search by rememberSaveable { mutableStateOf("") }
    val archive = CatalogSearch.archives(data.archive, search, format)
    LazyColumn(Modifier.fillMaxSize(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        item {
            PageHeading("Arhiva objava",
                "Pregledajte prethodno objavljene cjenike i otvorite arhivirane datoteke.")
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("csv", "xml", "sve").forEach { option ->
                    FilterChip(
                        selected = format == option,
                        onClick = { format = option },
                        label = { Text(option.uppercase().let { if (option == "sve") "Sve" else it }) },
                        modifier = Modifier.weight(1f),
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = Accent, selectedLabelColor = Background),
                    )
                }
            }
            OutlinedTextField(
                value = search, onValueChange = { search = it },
                modifier = Modifier.fillMaxWidth(),
                leadingIcon = { Icon(Icons.Outlined.Search, null) },
                label = { Text("Pretraži arhivu") },
                singleLine = true,
            )
            Spacer(Modifier.height(10.dp))
            if (archive.isNotEmpty()) {
                Panel {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            AppIcon(Icons.Outlined.Description, SoftBlue)
                            Spacer(Modifier.width(12.dp))
                            Column {
                                Text("Zadnji arhivirani cjenik", fontWeight = FontWeight.Bold)
                                MetaText(archive.first().date)
                            }
                        }
                        ArchiveSummary(archive.first())
                        OutlineAction("Otvori", { onOpen(archive.first().url) }, Modifier.fillMaxWidth())
                    }
                }
                Spacer(Modifier.height(12.dp))
            }
            MetaText("Prikazano " + archive.size + " arhivskih datoteka")
        }
        if (archive.isEmpty()) item { Panel { MetaText("Za odabrani prikaz nema arhivskih datoteka.") } }
        items(archive.drop(if (archive.isNotEmpty()) 1 else 0)) { entry ->
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = Surface),
                border = BorderStroke(1.dp, Border),
                shape = RoundedCornerShape(16.dp),
            ) {
                Row(Modifier.padding(13.dp), verticalAlignment = Alignment.CenterVertically) {
                    AppIcon(Icons.Outlined.Description, if (entry.format == "xml") Violet else SoftBlue)
                    Spacer(Modifier.width(10.dp))
                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(5.dp)) {
                        Text(entry.date, fontWeight = FontWeight.Bold)
                        ArchiveSummary(entry)
                    }
                    TextButton(onClick = { onOpen(entry.url) }) { Text("Otvori", color = Accent) }
                }
            }
        }
        item { Spacer(Modifier.height(14.dp)) }
    }
}

@Composable
private fun ArchiveSummary(entry: ArchiveEntry) {
    MetaText(
        (if (entry.kind.equals("usluge", true)) "Usluge" else "Proizvodi") +
            " · " + entry.outlet + " · " + entry.format.uppercase()
    )
}

@Composable
fun LocationsPage(
    data: CatalogSnapshot,
    selectedOutlet: String,
    busy: Boolean,
    onSelect: (String) -> Unit,
) {
    val outlets = data.index.outlets
    LazyColumn(Modifier.fillMaxSize(), verticalArrangement = Arrangement.spacedBy(11.dp)) {
        item {
            PageHeading("Poslovnice i kanali", "Pregledajte objavljene cjenike prema poslovnici ili webshopu.")
            Panel {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    AppIcon(Icons.Outlined.LocationOn)
                    Spacer(Modifier.width(12.dp))
                    Column {
                        Text((outlets.size + 1).toString(),
                            style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                        MetaText("Webshop i povezane poslovnice")
                    }
                }
            }
            Spacer(Modifier.height(3.dp))
        }
        item {
            OutletCard("Webshop", "Glavni javni cjenik", selectedOutlet.isEmpty(), busy) {
                onSelect("")
            }
        }
        items(outlets) { outlet ->
            OutletCard(outlet.name, "Šifra: " + outlet.code,
                selectedOutlet == outlet.code, busy) { onSelect(outlet.code) }
        }
        item {
            Panel {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("Poslovnice i javni cjenici", fontWeight = FontWeight.Bold)
                    MetaText("Prikazuju se poslovnice objavljene na povezanoj WordPress stranici.")
                }
            }
            Spacer(Modifier.height(16.dp))
        }
    }
}

@Composable
private fun OutletCard(
    name: String, detail: String, active: Boolean, busy: Boolean, onClick: () -> Unit,
) {
    Card(
        modifier = Modifier.fillMaxWidth().clickable(enabled = !busy, onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = Surface),
        border = BorderStroke(1.dp, if (active) Accent else Border),
        shape = RoundedCornerShape(16.dp),
    ) {
        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            AppIcon(if (name == "Webshop") Icons.Outlined.Store else Icons.Outlined.LocationOn)
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(name, fontWeight = FontWeight.Bold)
                MetaText(detail)
                if (active) Pill("Odabrano")
            }
            TextButton(enabled = !busy, onClick = onClick) { Text("Pregledaj", color = Accent) }
        }
    }
}

@Composable
fun SearchPage(
    data: CatalogSnapshot,
    onDetails: (PriceItem) -> Unit,
    onBack: () -> Unit,
    onArchive: () -> Unit,
) {
    var query by rememberSaveable { mutableStateOf("") }
    var kind by rememberSaveable { mutableIntStateOf(0) }
    val filtered = CatalogSearch.items(
        if (kind == 1) data.services else data.products, query
    )
    LazyColumn(Modifier.fillMaxSize(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        item {
            PageHeading("Pretraživanje")
            OutlinedTextField(
                value = query, onValueChange = { query = it },
                modifier = Modifier.fillMaxWidth(),
                placeholder = { Text("Naziv, SKU ili ID") },
                leadingIcon = { Icon(Icons.Outlined.Search, null) },
                singleLine = true,
            )
            Spacer(Modifier.height(12.dp))
            Row(Modifier.horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("Proizvodi", "Usluge", "Arhiva").forEachIndexed { index, label ->
                    FilterChip(
                        selected = kind == index,
                        onClick = { if (index == 2) onArchive() else kind = index },
                        label = { Text(label) },
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = Accent, selectedLabelColor = Background)
                    )
                }
            }
            MetaText("Pronađeno " + filtered.size + " rezultata")
        }
        items(filtered) { item ->
            Card(
                modifier = Modifier.fillMaxWidth().clickable { onDetails(item) },
                colors = CardDefaults.cardColors(containerColor = Surface),
                border = BorderStroke(1.dp, Border),
                shape = RoundedCornerShape(16.dp),
            ) {
                Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                    AppIcon(if (kind == 1) Icons.Outlined.Settings else Icons.Outlined.Inventory2)
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        MetaText(if (kind == 1) "Usluga" else "Proizvod")
                        Text(item.name, fontWeight = FontWeight.Bold)
                        MetaText(formatPrice(item.currentPrice))
                    }
                    Text("›", color = TextMuted, style = MaterialTheme.typography.titleLarge)
                }
            }
        }
        item {
            if (filtered.isEmpty()) Panel { MetaText("Nema rezultata pretraživanja.") }
            OutlineAction("Natrag", onBack, Modifier.fillMaxWidth())
            Spacer(Modifier.height(15.dp))
        }
    }
}
