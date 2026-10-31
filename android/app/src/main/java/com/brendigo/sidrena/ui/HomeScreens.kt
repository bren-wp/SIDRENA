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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Archive
import androidx.compose.material.icons.outlined.CloudDone
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Inventory2
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.Public
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material.icons.outlined.Sync
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.brendigo.sidrena.data.CatalogSnapshot

@Composable
private fun DashboardMetric(
    label: String,
    value: String,
    icon: ImageVector,
    tint: Color = Accent,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Card(
        modifier = modifier.clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        border = BorderStroke(1.dp, Border),
        colors = CardDefaults.cardColors(containerColor = Surface),
    ) {
        Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(7.dp)) {
            androidx.compose.material3.Icon(icon, contentDescription = null, tint = tint,
                modifier = Modifier.size(26.dp))
            Text(value, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text(label, style = MaterialTheme.typography.labelSmall, color = TextMuted,
                maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
    }
}

@Composable
fun HomePage(
    data: CatalogSnapshot,
    busy: Boolean,
    error: String?,
    onNavigate: (String) -> Unit,
    onRefresh: () -> Unit,
    onEdit: () -> Unit,
    onOpen: (String) -> Unit,
) {
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        PageHeading("Cijene pod kontrolom",
            "Cjenici, objave i poslovnice na jednom mjestu.")
        Panel {
            Row(verticalAlignment = Alignment.CenterVertically) {
                AppIcon(Icons.Outlined.Public, SoftBlue, 50)
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text(data.site.removePrefix("https://").removeSuffix("/"),
                        fontWeight = FontWeight.Bold, maxLines = 1,
                        overflow = TextOverflow.Ellipsis)
                    MetaText("Povezana WordPress stranica")
                    Pill("Povezano")
                }
                TextButton(onClick = onEdit) { Text("Promijeni", color = SoftBlue) }
            }
        }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            DashboardMetric("Proizvodi", data.products.size.toString(),
                Icons.Outlined.Inventory2, Accent, { onNavigate("products") }, Modifier.weight(1f))
            DashboardMetric("Usluge", data.services.size.toString(),
                Icons.Outlined.Settings, SoftBlue, { onNavigate("services") }, Modifier.weight(1f))
            DashboardMetric("Poslovnice", data.index.outlets.size.toString(),
                Icons.Outlined.LocationOn, Accent, { onNavigate("locations") }, Modifier.weight(1f))
            DashboardMetric("Arhiva", data.archive.size.toString(),
                Icons.Outlined.Archive, Violet, { onNavigate("archive") }, Modifier.weight(1f))
        }
        Panel {
            Column(verticalArrangement = Arrangement.spacedBy(9.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    AppIcon(Icons.Outlined.CloudDone, SoftBlue)
                    Spacer(Modifier.width(10.dp))
                    Column(Modifier.weight(1f)) {
                        Text("Zadnja objava", fontWeight = FontWeight.Bold)
                        MetaText(data.index.publishedAt ?: "Datum nije dostupan")
                    }
                    if (data.index.publishedAt != null) Pill("Objavljeno")
                }
                MetaText("Prikaz objavljenih podataka sa spojene stranice.")
                data.index.publicPage?.let { link ->
                    AccentButton("Otvori javni cjenik", { onOpen(link) }, Modifier.fillMaxWidth())
                }
            }
        }
        if (busy) Row(verticalAlignment = Alignment.CenterVertically) {
            CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp)
            Spacer(Modifier.width(8.dp))
            MetaText("Osvježavanje…")
        }
        error?.let { Text(it, color = Color(0xFFFFBD9A)) }
        data.warnings.take(3).forEach { Text(it, color = Color(0xFFFFC997),
            style = MaterialTheme.typography.bodySmall) }
        Panel {
            Column(verticalArrangement = Arrangement.spacedBy(7.dp)) {
                Text("Sidrene cijene — pravni okvir", fontWeight = FontWeight.Bold)
                data.index.legal?.let { legal ->
                    MetaText("Početak primjene: " + legal.effectiveFrom +
                        " · NN 101/2026 i NN 110/2026")
                    MetaText("2. 5. 2025. — postojeće kategorije  |  10. 9. 2026. — ostali proizvodi i usluge")
                    MetaText("Arhiva najmanje " + legal.archiveMinDays +
                        " dana · planirana objava do " + legal.dailyDeadline)
                } ?: MetaText("Provjerite ažuriranje WordPress dodatka i primjenjive propise.")
                MetaText("Točnost povijesnih cijena i zakonsku primjenu treba provjeriti prema izvornoj dokumentaciji.")
            }
        }
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text("Brzi pristup", fontWeight = FontWeight.Bold,
                style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
            TextButton(onClick = onRefresh, enabled = !busy) {
                androidx.compose.material3.Icon(Icons.Outlined.Sync, null, tint = SoftBlue)
                Text("Osvježi", color = SoftBlue)
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Card(modifier = Modifier.weight(1f).clickable { onNavigate("products") },
                colors = CardDefaults.cardColors(containerColor = Surface),
                border = BorderStroke(1.dp, Border)) {
                Column(Modifier.padding(13.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    AppIcon(Icons.Outlined.Inventory2)
                    Text("Proizvodi", fontWeight = FontWeight.Bold)
                    MetaText("Pregledajte aktualne i sidrene cijene")
                }
            }
            Card(modifier = Modifier.weight(1f).clickable { onNavigate("services") },
                colors = CardDefaults.cardColors(containerColor = Surface),
                border = BorderStroke(1.dp, Border)) {
                Column(Modifier.padding(13.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    AppIcon(Icons.Outlined.Settings, SoftBlue)
                    Text("Usluge", fontWeight = FontWeight.Bold)
                    MetaText("Pronađite objavljene usluge i cijene")
                }
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Card(modifier = Modifier.weight(1f).clickable { onNavigate("archive") },
                colors = CardDefaults.cardColors(containerColor = Surface),
                border = BorderStroke(1.dp, Border)) {
                Column(Modifier.padding(13.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    AppIcon(Icons.Outlined.Archive, SoftBlue)
                    Text("Arhiva", fontWeight = FontWeight.Bold)
                    MetaText("Prethodne objave i datoteke")
                }
            }
            Card(modifier = Modifier.weight(1f).clickable { onNavigate("locations") },
                colors = CardDefaults.cardColors(containerColor = Surface),
                border = BorderStroke(1.dp, Border)) {
                Column(Modifier.padding(13.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    AppIcon(Icons.Outlined.LocationOn)
                    Text("Lokacije", fontWeight = FontWeight.Bold)
                    MetaText("Poslovnice i prodajni kanali")
                }
            }
        }
        Panel {
            Row(verticalAlignment = Alignment.CenterVertically) {
                AppIcon(Icons.Outlined.Description, SoftBlue, 62)
                Spacer(Modifier.width(12.dp))
                Column {
                    Text("Transparentni javni cjenici", fontWeight = FontWeight.Bold)
                    MetaText("Pregledajte objavljene cijene i povijesne podatke s WordPress stranice.")
                }
            }
        }
        Spacer(Modifier.height(12.dp))
    }
}
