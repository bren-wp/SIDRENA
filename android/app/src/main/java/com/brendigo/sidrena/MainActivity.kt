package com.brendigo.sidrena

import android.content.ActivityNotFoundException
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.systemBarsPadding
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.brendigo.sidrena.data.ArchiveEntry
import com.brendigo.sidrena.data.CatalogSnapshot
import com.brendigo.sidrena.data.PriceItem
import com.brendigo.sidrena.data.SiteUrl

private val Navy = Color(0xFF10243A)
private val Elevated = Color(0xFF1A3551)
private val Orange = Color(0xFFF07837)
private val TextSoft = Color(0xFFB9C7D5)

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme(
                colorScheme = darkColorScheme(
                    primary = Orange,
                    onPrimary = Navy,
                    secondary = Orange,
                    background = Navy,
                    surface = Navy,
                    surfaceVariant = Elevated,
                    onSurface = Color.White,
                    onSurfaceVariant = TextSoft,
                ),
            ) {
                SidrenaScreen()
            }
        }
    }
}

@Composable
private fun SidrenaScreen(model: SidrenaViewModel = viewModel()) {
    val state = model.state
    val snapshot = state.snapshot
    var tab by rememberSaveable { mutableIntStateOf(0) }
    var search by rememberSaveable { mutableStateOf("") }
    val context = LocalContext.current

    Column(
        modifier = Modifier.fillMaxSize().background(Navy).systemBarsPadding().imePadding(),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Column(Modifier.fillMaxSize().widthIn(max = 940.dp).padding(horizontal = 18.dp)) {
            Spacer(Modifier.height(14.dp))
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                Box(
                    modifier = Modifier.size(46.dp).background(Orange, RoundedCornerShape(14.dp)),
                    contentAlignment = Alignment.Center,
                ) {
                    Text("S", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Black, color = Navy)
                }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text("SIDRENA", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Text("Brendigo · Cjenici pod kontrolom", style = MaterialTheme.typography.labelSmall, color = TextSoft)
                }
                if (snapshot != null && !state.isEditingSite) {
                    TextButton(onClick = model::editSite) { Text("Stranica") }
                }
            }
            Spacer(Modifier.height(16.dp))

            if (state.isEditingSite || snapshot == null) {
                ConnectionScreen(
                    input = state.siteInput,
                    busy = state.isLoading,
                    error = state.error,
                    canCancel = snapshot != null,
                    onInput = model::setSiteInput,
                    onConnect = { model.connect() },
                    onCancel = model::cancelEdit,
                )
            } else {
                Dashboard(
                    snapshot = snapshot,
                    state = state,
                    tab = tab,
                    search = search,
                    onTab = { tab = it; search = "" },
                    onSearch = { search = it },
                    onOutlet = model::selectOutlet,
                    onRefresh = model::refresh,
                    onOpen = { url ->
                        val verified = runCatching { SiteUrl.parse(snapshot.site).publicLink(url) }.getOrNull()
                        if (verified == null) {
                            Toast.makeText(context, "Poveznica nije dopuštena.", Toast.LENGTH_SHORT).show()
                        } else {
                            try {
                                context.startActivity(
                                    Intent(Intent.ACTION_VIEW, Uri.parse(verified)).addCategory(Intent.CATEGORY_BROWSABLE),
                                )
                            } catch (_: ActivityNotFoundException) {
                                Toast.makeText(context, "Nema aplikacije za otvaranje poveznice.", Toast.LENGTH_SHORT).show()
                            }
                        }
                    },
                )
            }
        }
    }
}

@Composable
private fun ConnectionScreen(
    input: String,
    busy: Boolean,
    error: String?,
    canCancel: Boolean,
    onInput: (String) -> Unit,
    onConnect: () -> Unit,
    onCancel: () -> Unit,
) {
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Card(colors = CardDefaults.cardColors(containerColor = Elevated), shape = RoundedCornerShape(22.dp)) {
            Column(Modifier.fillMaxWidth().padding(22.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("Povežite svoju WordPress stranicu", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold)
                Text(
                    "Pregledajte javno objavljene proizvode, usluge i arhivu postojećeg dodatka SIDRENA. " +
                        "Za ovaj način rada nije potrebna prijava.",
                    color = TextSoft,
                )
                OutlinedTextField(
                    value = input,
                    onValueChange = onInput,
                    label = { Text("HTTPS adresa stranice") },
                    placeholder = { Text("https://primjer.hr") },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
                    modifier = Modifier.fillMaxWidth(),
                    enabled = !busy,
                )
                if (error != null) {
                    Text(error, color = Color(0xFFFFB6A5), style = MaterialTheme.typography.bodySmall)
                }
                Button(onClick = onConnect, enabled = !busy && input.isNotBlank(), modifier = Modifier.fillMaxWidth()) {
                    Text("Poveži i učitaj cjenike")
                }
                if (busy) Row(verticalAlignment = Alignment.CenterVertically) {
                    CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
                    Spacer(Modifier.width(12.dp))
                    Text("Učitavanje javnih cjenika…", color = TextSoft)
                }
                if (canCancel) TextButton(onClick = onCancel, enabled = !busy) { Text("Natrag na pregled") }
            }
        }
        Text(
            "Samo čitanje · HTTPS veza · Bez oglasa i vanjske analitike. " +
                "Podaci ostaju na vašoj WordPress stranici; aplikacija ih ne mijenja.",
            color = TextSoft, style = MaterialTheme.typography.bodySmall,
        )
    }
}

@Composable
private fun ColumnScope.Dashboard(
    snapshot: CatalogSnapshot,
    state: SidrenaState,
    tab: Int,
    search: String,
    onTab: (Int) -> Unit,
    onSearch: (String) -> Unit,
    onOutlet: (String) -> Unit,
    onRefresh: () -> Unit,
    onOpen: (String) -> Unit,
) {
    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.weight(1f)) {
            Text("Objavljeni cjenici", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Text(snapshot.site, style = MaterialTheme.typography.labelMedium, color = TextSoft, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        TextButton(onClick = onRefresh, enabled = !state.isLoading) { Text("Osvježi") }
    }
    Spacer(Modifier.height(12.dp))
    Card(colors = CardDefaults.cardColors(containerColor = Elevated), shape = RoundedCornerShape(16.dp)) {
        Column(Modifier.fillMaxWidth().padding(16.dp)) {
            Text("Zadnja objava", style = MaterialTheme.typography.labelMedium, color = TextSoft)
            Text(snapshot.index.publishedAt ?: "Nije navedeno", fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(10.dp))
            Text(
                snapshot.products.size.toString() + " proizvoda  ·  " +
                    snapshot.services.size.toString() + " usluga  ·  " +
                    snapshot.archive.size.toString() + " arhivskih datoteka",
                style = MaterialTheme.typography.bodySmall,
            )
            snapshot.index.publicPage?.let { link ->
                TextButton(onClick = { onOpen(link) }, contentPadding = PaddingValues(0.dp)) {
                    Text("Javni cjenik ↗")
                }
            }
        }
    }
    if (state.isLoading) {
        Spacer(Modifier.height(8.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp)
            Spacer(Modifier.width(8.dp))
            Text("Ažuriranje podataka…", color = TextSoft, style = MaterialTheme.typography.labelSmall)
        }
    }
    if (state.error != null) Text(state.error, color = Color(0xFFFFB6A5), style = MaterialTheme.typography.bodySmall)
    snapshot.warnings.forEach { warning ->
        Text(warning, color = Color(0xFFFFCB94), style = MaterialTheme.typography.bodySmall)
    }
    if (snapshot.index.outlets.isNotEmpty()) {
        Spacer(Modifier.height(10.dp))
        Text("Poslovnica", style = MaterialTheme.typography.labelMedium, color = TextSoft)
        Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            FilterChip(selected = state.outlet == "", onClick = { onOutlet("") }, label = { Text("Webshop") }, enabled = !state.isLoading)
            snapshot.index.outlets.forEach { outlet ->
                FilterChip(
                    selected = state.outlet == outlet.code, onClick = { onOutlet(outlet.code) },
                    label = { Text(outlet.name) }, enabled = !state.isLoading,
                )
            }
        }
    }
    Spacer(Modifier.height(10.dp))
    Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        listOf("Proizvodi", "Usluge", "Arhiva").forEachIndexed { index, label ->
            FilterChip(selected = index == tab, onClick = { onTab(index) }, label = { Text(label) })
        }
    }
    Spacer(Modifier.height(8.dp))
    OutlinedTextField(
        value = search,
        onValueChange = onSearch,
        modifier = Modifier.fillMaxWidth(),
        singleLine = true,
        label = { Text(if (tab == 2) "Pretraži arhivu" else "Pretraži naziv, SKU ili ID") },
    )
    Spacer(Modifier.height(12.dp))
    val items = if (tab == 0) snapshot.products else snapshot.services
    if (tab == 2) {
        val filtered = snapshot.archive.filter {
            (it.date + " " + it.kind + " " + it.outlet + " " + it.format).contains(search, ignoreCase = true)
        }
        if (filtered.isEmpty()) EmptyState("Nema objavljenih arhivskih datoteka.")
        else LazyColumn(
            modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(10.dp),
            contentPadding = PaddingValues(bottom = 26.dp),
        ) {
            items(filtered) { entry -> ArchiveCard(entry, onOpen) }
        }
    } else {
        val filtered = items.filter {
            (it.name + " " + it.sku + " " + it.id).contains(search, ignoreCase = true)
        }
        if (filtered.isEmpty()) EmptyState("Nema objavljenih stavki za odabrani prikaz.")
        else LazyColumn(
            modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(10.dp),
            contentPadding = PaddingValues(bottom = 26.dp),
        ) {
            items(filtered) { item -> PriceCard(item) }
        }
    }
}

@Composable
private fun PriceCard(item: PriceItem) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = Elevated),
        shape = RoundedCornerShape(18.dp),
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(item.name.ifBlank { "Bez naziva" }, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
            val metadata = listOfNotNull(
                item.sku.takeIf { it.isNotBlank() }?.let { "SKU: " + it },
                item.availability.takeIf { it.isNotBlank() },
                item.unit.takeIf { it.isNotBlank() }?.let { "Jedinica: " + it },
            ).joinToString(" · ")
            if (metadata.isNotEmpty()) Text(metadata, color = TextSoft, style = MaterialTheme.typography.bodySmall)
            HorizontalDivider(color = TextSoft.copy(alpha = 0.15f))
            Row(horizontalArrangement = Arrangement.spacedBy(20.dp), modifier = Modifier.fillMaxWidth()) {
                PriceColumn("Trenutačna cijena", item.currentPrice, Modifier.weight(1f), true)
                PriceColumn("Sidrena cijena", item.referencePrice, Modifier.weight(1f), false)
            }
            if (item.referenceDate.isNotBlank()) {
                Text("Datum sidrene cijene: " + item.referenceDate, color = TextSoft, style = MaterialTheme.typography.labelSmall)
            }
        }
    }
}

@Composable
private fun PriceColumn(label: String, price: String, modifier: Modifier, highlighted: Boolean) {
    Column(modifier) {
        Text(label, color = TextSoft, style = MaterialTheme.typography.labelSmall)
        Text(
            if (price.isBlank()) "Nije unesena" else price + " €",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold,
            color = if (highlighted) Orange else Color.White,
        )
    }
}

@Composable
private fun ArchiveCard(item: ArchiveEntry, onOpen: (String) -> Unit) {
    Card(colors = CardDefaults.cardColors(containerColor = Elevated), shape = RoundedCornerShape(16.dp)) {
        Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(item.date, fontWeight = FontWeight.Bold)
                Text(
                    (if (item.kind == "usluge") "Usluge" else "Proizvodi") +
                        " · " + item.outlet + " · " + item.format.uppercase(),
                    style = MaterialTheme.typography.labelSmall, color = TextSoft,
                )
            }
            TextButton(onClick = { onOpen(item.url) }) { Text("Otvori ↗") }
        }
    }
}

@Composable
private fun EmptyState(message: String) {
    Surface(color = Elevated, shape = RoundedCornerShape(16.dp), modifier = Modifier.fillMaxWidth()) {
        Text(message, modifier = Modifier.padding(24.dp), color = TextSoft)
    }
}
