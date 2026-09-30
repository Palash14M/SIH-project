package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Toast;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatActivity;
import com.chakravyuh.smartinspection.SmartInspectionApp;
import com.chakravyuh.smartinspection.util.PreferenceManager;

public abstract class BaseActivity extends AppCompatActivity {
    protected PreferenceManager preferenceManager;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        preferenceManager = SmartInspectionApp.getInstance().getPreferenceManager();
    }

    protected void showToast(String message) {
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show();
    }

    protected void showError(String message) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show();
    }
}
