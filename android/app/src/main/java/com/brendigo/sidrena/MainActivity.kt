package com.brendigo.sidrena

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import com.brendigo.sidrena.ui.SidrenaApp
import com.brendigo.sidrena.ui.SidrenaTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            SidrenaTheme { SidrenaApp() }
        }
    }
}
