package com.brendigo.sidrena.ui

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.brendigo.sidrena.R

/**
 * Screen 01: SIDRENA branding splash.
 *
 * Shows an animated loading indicator once per activity creation and then
 * delegates navigation to the caller. No artificial network work is performed
 * and the existing WordPress connection/application state is left untouched.
 */
@Composable
fun SidrenaSplashScreen(
    onFinished: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val progress = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        progress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 2200, easing = LinearEasing),
        )
        onFinished()
    }
    SidrenaSplashContent(progress = progress.value, modifier = modifier)
}

/** UI-only version so previews can inspect a stable animation frame. */
@Composable
internal fun SidrenaSplashContent(
    progress: Float,
    modifier: Modifier = Modifier,
) {
    BoxWithConstraints(
        modifier = modifier.fillMaxSize().background(Background),
    ) {
        val compact = maxHeight < 650.dp
        Canvas(Modifier.matchParentSize()) {
            val w = size.width
            val h = size.height

            // Angular navy shapes are intentional SIDRENA brand decorations.
            val upper = Path().apply {
                moveTo(0f, 0f)
                lineTo(w * 0.93f, 0f)
                lineTo(w * 0.26f, h * 0.34f)
                lineTo(0f, h * 0.20f)
                close()
            }
            drawPath(
                upper,
                brush = Brush.linearGradient(
                    listOf(Color(0xFF123457), Color(0xFF0A2138)),
                    start = Offset.Zero,
                    end = Offset(w * 0.82f, h * 0.34f),
                ),
            )
            val lower = Path().apply {
                moveTo(0f, h * 0.82f)
                lineTo(w, h * 0.65f)
                lineTo(w, h)
                lineTo(0f, h)
                close()
            }
            drawPath(
                lower,
                brush = Brush.linearGradient(
                    listOf(Color(0xFF102C48), Color(0xFF081A2D)),
                    start = Offset(w, h * 0.69f),
                    end = Offset(0f, h),
                ),
            )
            val orangeCorner = Path().apply {
                moveTo(w, h * 0.76f)
                lineTo(w, h * 0.88f)
                lineTo(w * 0.75f, h * 0.87f)
                close()
            }
            drawPath(orangeCorner, Accent.copy(alpha = 0.14f))
        }

        Column(
            modifier = Modifier
                .align(Alignment.Center)
                .offset(y = if (compact) (-12).dp else (-44).dp)
                .padding(horizontal = 24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            val iconShape = RoundedCornerShape(26.dp)
            Box(
                modifier = Modifier
                    .size(if (compact) 100.dp else 116.dp)
                    .clip(iconShape)
                    .background(
                        Brush.verticalGradient(
                            listOf(Color(0xFF0B2743), Color(0xFF061528)),
                        ),
                    )
                    .border(1.dp, Color(0xFF3778B8), iconShape),
                contentAlignment = Alignment.Center,
            ) {
                Image(
                    painter = painterResource(id = R.drawable.ic_sidrena_mark),
                    contentDescription = "SIDRENA logotip",
                    modifier = Modifier.size(if (compact) 75.dp else 88.dp),
                )
            }
            Spacer(Modifier.height(if (compact) 16.dp else 22.dp))
            Text(
                text = "SIDRENA",
                style = TextStyle(
                    color = Color.White,
                    fontSize = if (compact) 30.sp else 36.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.1.sp,
                ),
            )
            Spacer(Modifier.height(9.dp))
            Text(
                text = "Cijene pod kontrolom.\nPoslovanje s povjerenjem.",
                style = TextStyle(
                    color = Color(0xFFE0E9F2),
                    fontSize = 13.sp,
                    lineHeight = 19.sp,
                    textAlign = TextAlign.Center,
                ),
            )
        }

        Column(
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .padding(bottom = if (compact) 20.dp else 38.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                text = "by Brendigo",
                color = Color(0xFFD3DFED),
                fontSize = 12.sp,
            )
            Spacer(Modifier.height(20.dp))
            Box(
                modifier = Modifier
                    .width(96.dp)
                    .height(4.dp)
                    .clip(RoundedCornerShape(50))
                    .background(Color(0xFF28455F)),
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxHeight()
                        .fillMaxWidth(progress.coerceIn(0f, 1f))
                        .background(Accent),
                )
            }
        }
    }
}

@Preview(name = "SIDRENA Splash - 393x852", widthDp = 393, heightDp = 852, showBackground = true)
@Composable
internal fun SidrenaSplashPreview() {
    SidrenaTheme {
        SidrenaSplashContent(progress = 0.46f)
    }
}

@Preview(name = "SIDRENA Splash - compact", widthDp = 360, heightDp = 640, showBackground = true)
@Composable
internal fun SidrenaSplashCompactPreview() {
    SidrenaTheme {
        SidrenaSplashContent(progress = 0.46f)
    }
}
