package com.brendigo.sidrena.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Link
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * Screen 02: connection to a public WordPress + SIDRENA site.
 *
 * The display follows the approved 10-screen branding board:
 * back arrow, centered link emblem, explanatory copy, HTTPS input,
 * prominent orange CTA, and a public-data information panel.
 * Validation and real network requests remain in SidrenaViewModel.
 */
@Composable
fun ConnectionPage(
    input: String,
    busy: Boolean,
    error: String?,
    canCancel: Boolean,
    onInput: (String) -> Unit,
    onConnect: (String) -> Unit,
    onCancel: () -> Unit,
) {
    val keyboard = LocalSoftwareKeyboardController.current

    BoxWithConstraints(
        modifier = Modifier.fillMaxSize().background(Background),
    ) {
        val compact = maxHeight < 665.dp

        Column(
            modifier = Modifier
                .fillMaxSize()
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Spacer(Modifier.height(if (compact) 4.dp else 13.dp))

            Row(
                modifier = Modifier.fillMaxWidth().height(48.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(
                    onClick = onCancel,
                    modifier = Modifier.size(48.dp),
                ) {
                    Icon(
                        imageVector = Icons.Outlined.ArrowBack,
                        contentDescription = if (canCancel) "Povratak" else "Zatvori aplikaciju",
                        modifier = Modifier.size(26.dp),
                        tint = Color.White,
                    )
                }
            }

            Spacer(Modifier.height(if (compact) 6.dp else 10.dp))

            Text(
                text = "Poveži stranicu",
                modifier = Modifier.fillMaxWidth(),
                color = Color.White,
                style = TextStyle(
                    fontSize = 27.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = (-0.6).sp,
                ),
            )

            Spacer(Modifier.height(if (compact) 20.dp else 30.dp))

            Box(
                modifier = Modifier
                    .size(if (compact) 104.dp else 118.dp)
                    .background(
                        brush = Brush.verticalGradient(
                            listOf(Color(0xFF193759), Color(0xFF10263D)),
                        ),
                        shape = CircleShape,
                    )
                    .border(
                        border = BorderStroke(2.dp, Color(0xFF3977B5)),
                        shape = CircleShape,
                    ),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    imageVector = Icons.Outlined.Link,
                    contentDescription = null,
                    modifier = Modifier.size(if (compact) 56.dp else 66.dp),
                    tint = Color.White,
                )
            }

            Spacer(Modifier.height(if (compact) 17.dp else 23.dp))

            Text(
                text = "Unesite URL vaše\nWordPress stranice",
                color = Color.White,
                textAlign = TextAlign.Center,
                style = TextStyle(
                    fontSize = 21.sp,
                    fontWeight = FontWeight.Bold,
                    lineHeight = 27.sp,
                ),
            )

            Spacer(Modifier.height(if (compact) 11.dp else 16.dp))

            Text(
                text = "Povežite se s web-stranicom kako biste pregledavali javno objavljene proizvode, usluge, cjenike i poslovnice.",
                color = TextMuted,
                textAlign = TextAlign.Center,
                style = TextStyle(
                    fontSize = 14.sp,
                    lineHeight = 20.sp,
                ),
            )

            Spacer(Modifier.height(if (compact) 20.dp else 28.dp))

            OutlinedTextField(
                value = input,
                onValueChange = { value ->
                    if (value.length <= 2048 && !value.contains('\n')) onInput(value)
                },
                modifier = Modifier.fillMaxWidth().height(64.dp)\n                    .semantics { contentDescription = "HTTPS adresa WordPress stranice" },
                textStyle = TextStyle(
                    fontSize = 17.sp,
                    color = Color.White,
                    fontWeight = FontWeight.Medium,
                ),
                placeholder = {
                    Text(
                        text = "https://",
                        color = TextMuted,
                        fontSize = 17.sp,
                    )
                },
                leadingIcon = {
                    Icon(
                        imageVector = Icons.Outlined.Link,
                        contentDescription = null,
                        modifier = Modifier.size(25.dp),
                    )
                },
                label = null,
                singleLine = true,
                maxLines = 1,
                enabled = !busy,
                shape = RoundedCornerShape(14.dp),
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Uri,
                    imeAction = ImeAction.Done,
                ),
                keyboardActions = KeyboardActions(
                    onDone = {
                        keyboard?.hide()
                        if (!busy && input.isNotBlank()) onConnect(input.trim())
                    },
                ),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = SoftBlue,
                    unfocusedBorderColor = Border,
                    disabledBorderColor = Border,
                    focusedContainerColor = SurfaceRaised,
                    unfocusedContainerColor = SurfaceRaised,
                    disabledContainerColor = SurfaceRaised,
                    focusedLeadingIconColor = Color.White,
                    unfocusedLeadingIconColor = Color.White,
                    disabledLeadingIconColor = TextMuted,
                    cursorColor = Accent,
                ),
                supportingText = null,
            )

            Spacer(Modifier.height(if (compact) 15.dp else 17.dp))

            Button(
                onClick = {
                    keyboard?.hide()
                    onConnect(input.trim())
                },
                enabled = !busy && input.isNotBlank(),
                modifier = Modifier.fillMaxWidth().height(56.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Accent,
                    contentColor = Background,
                    disabledContainerColor = Accent.copy(alpha = 0.45f),
                    disabledContentColor = Background.copy(alpha = 0.7f),
                ),
            ) {
                if (busy) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(21.dp),
                        color = Background,
                        strokeWidth = 2.dp,
                    )
                    Spacer(Modifier.size(9.dp))
                }
                Text(
                    text = if (busy) "Povezivanje…" else "Poveži se",
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                )
            }

            if (!error.isNullOrBlank()) {
                Spacer(Modifier.height(12.dp))
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = Surface),
                    border = BorderStroke(1.dp, Accent.copy(alpha = 0.7f)),
                    shape = RoundedCornerShape(12.dp),
                ) {
                    Text(
                        text = error,
                        color = Color(0xFFFFC6A0),
                        fontSize = 14.sp,
                        lineHeight = 20.sp,
                        modifier = Modifier.padding(15.dp),
                    )
                }
            }

            Spacer(Modifier.height(if (compact) 23.dp else 32.dp))

            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = Surface),
                border = BorderStroke(1.dp, Border),
                shape = RoundedCornerShape(14.dp),
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth().padding(17.dp),
                    verticalAlignment = Alignment.Top,
                    horizontalArrangement = Arrangement.spacedBy(13.dp),
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Info,
                        contentDescription = null,
                        tint = Color.White,
                        modifier = Modifier.size(26.dp),
                    )
                    Text(
                        text = "Potrebno je da na vašoj stranici budu dostupni javni podaci (proizvodi, usluge, cjenici, poslovnice).",
                        color = Color(0xFFD3DFED),
                        fontSize = 13.sp,
                        lineHeight = 19.sp,
                    )
                }
            }

            Spacer(Modifier.height(if (compact) 18.dp else 32.dp))
        }
    }
}

@Preview(name = "02 Poveži stranicu • 393 × 852", widthDp = 393, heightDp = 852, showBackground = true)
@Composable
internal fun SidrenaConnectPreview() {
    SidrenaTheme {
        ConnectionPage(
            input = "",
            busy = false,
            error = null,
            canCancel = false,
            onInput = {},
            onConnect = {},
            onCancel = {},
        )
    }
}

@Preview(name = "02 Poveži stranicu • 360 × 640", widthDp = 360, heightDp = 640, showBackground = true)
@Composable
internal fun SidrenaConnectCompactPreview() {
    SidrenaTheme {
        ConnectionPage(
            input = "https://primjer-trgovina.hr",
            busy = false,
            error = null,
            canCancel = true,
            onInput = {},
            onConnect = {},
            onCancel = {},
        )
    }
}
