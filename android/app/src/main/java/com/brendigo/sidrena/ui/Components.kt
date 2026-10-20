package com.brendigo.sidrena.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Archive
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material.icons.outlined.NotificationsNone
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@Composable
fun BrandHeader(onNotifications: () -> Unit = {}) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(top = 10.dp, bottom = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier.size(44.dp).background(Accent, RoundedCornerShape(12.dp)),
            contentAlignment = Alignment.Center,
        ) {
            Text("S", color = Background, style = MaterialTheme.typography.headlineMedium,
                fontWeight = FontWeight.Black)
        }
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text("SIDRENA", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.ExtraBold,
                letterSpacing = 0.4.sp)
            Text("by Brendigo", style = MaterialTheme.typography.labelSmall, color = TextMuted)
        }
        Icon(
            Icons.Outlined.NotificationsNone, contentDescription = "Obavijesti",
            modifier = Modifier.size(28.dp).clickable(onClick = onNotifications), tint = TextMuted,
        )
    }
}

@Composable
fun PageHeading(title: String, subtitle: String? = null) {
    Text(title, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
    if (!subtitle.isNullOrEmpty()) {
        Spacer(Modifier.height(5.dp))
        Text(subtitle, style = MaterialTheme.typography.bodyMedium, color = TextMuted)
    }
    Spacer(Modifier.height(17.dp))
}

@Composable
fun Panel(modifier: Modifier = Modifier, content: @Composable () -> Unit) {
    Card(
        modifier = modifier.fillMaxWidth(), shape = RoundedCornerShape(18.dp),
        border = BorderStroke(1.dp, Border),
        colors = CardDefaults.cardColors(containerColor = Surface),
    ) {
        Box(Modifier.fillMaxWidth().padding(16.dp)) { content() }
    }
}

@Composable
fun AppIcon(icon: ImageVector, tint: Color = Accent, size: Int = 46) {
    Box(
        modifier = Modifier.size(size.dp)
            .background(SurfaceRaised, RoundedCornerShape(14.dp)),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, contentDescription = null, modifier = Modifier.size((size * 0.52f).dp), tint = tint)
    }
}

@Composable
fun AccentButton(label: String, onClick: () -> Unit, modifier: Modifier = Modifier, enabled: Boolean = true) {
    Button(
        onClick = onClick, enabled = enabled, modifier = modifier.height(48.dp),
        shape = RoundedCornerShape(14.dp),
        colors = ButtonDefaults.buttonColors(containerColor = Accent, contentColor = Background),
    ) { Text(label, fontWeight = FontWeight.Bold) }
}

@Composable
fun OutlineAction(label: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    OutlinedButton(
        onClick = onClick, modifier = modifier.height(48.dp),
        shape = RoundedCornerShape(14.dp),
        border = BorderStroke(1.dp, Border),
    ) { Text(label, color = TextMuted) }
}

@Composable
fun RowLink(icon: ImageVector, title: String, subtitle: String? = null,
            onClick: () -> Unit, accent: Color = SoftBlue) {
    Row(
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick).padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        AppIcon(icon, accent, 44)
        Column(Modifier.weight(1f)) {
            Text(title, fontWeight = FontWeight.SemiBold)
            if (!subtitle.isNullOrBlank()) Text(
                subtitle, color = TextMuted, style = MaterialTheme.typography.bodySmall,
                maxLines = 2, overflow = TextOverflow.Ellipsis,
            )
        }
        Icon(Icons.Outlined.ChevronRight, contentDescription = null, tint = TextMuted)
    }
}

@Composable
fun Separator() {
    HorizontalDivider(color = Border.copy(alpha = 0.65f), thickness = 0.6.dp)
}

@Composable
fun MetaText(text: String) {
    Text(text, style = MaterialTheme.typography.bodySmall, color = TextMuted)
}

@Composable
fun Pill(label: String, good: Boolean = true) {
    val color = if (good) Success else Accent
    Box(
        modifier = Modifier.background(color.copy(alpha = 0.10f), RoundedCornerShape(50))
            .padding(horizontal = 11.dp, vertical = 5.dp),
    ) {
        Text("●  $label", style = MaterialTheme.typography.labelSmall, color = color)
    }
}

@Composable
fun AppNavigation(active: Int, onSelect: (Int) -> Unit) {
    val tabs = listOf(
        Triple("Početna", Icons.Outlined.Home, 0),
        Triple("Cjenici", Icons.Outlined.Description, 1),
        Triple("Arhiva", Icons.Outlined.Archive, 2),
        Triple("Postavke", Icons.Outlined.Settings, 3),
    )
    NavigationBar(containerColor = Background, tonalElevation = 0.dp) {
        tabs.forEach { (label, icon, index) ->
            NavigationBarItem(
                selected = active == index,
                onClick = { onSelect(index) },
                icon = { Icon(icon, contentDescription = label) },
                label = { Text(label, maxLines = 1) },
                colors = NavigationBarItemDefaults.colors(
                    selectedIconColor = Accent, selectedTextColor = Accent,
                    indicatorColor = SurfaceRaised,
                    unselectedIconColor = TextMuted, unselectedTextColor = TextMuted,
                ),
                alwaysShowLabel = true,
            )
        }
    }
}
