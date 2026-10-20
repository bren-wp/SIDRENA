package com.brendigo.sidrena.ui

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

val Background = Color(0xFF07182D)
val Surface = Color(0xFF10263D)
val SurfaceRaised = Color(0xFF19304A)
val Border = Color(0xFF28455F)
val Accent = Color(0xFFFF852C)
val TextMuted = Color(0xFFA9BAD1)
val Success = Color(0xFF52D9A3)
val SoftBlue = Color(0xFF80B9FF)
val Violet = Color(0xFF9091FF)

@Composable
fun SidrenaTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = darkColorScheme(
            primary = Accent, onPrimary = Background,
            secondary = SoftBlue, background = Background,
            surface = Surface, surfaceVariant = SurfaceRaised,
            onSurface = Color.White, onSurfaceVariant = TextMuted,
            outline = Border,
        ),
        content = content,
    )
}
