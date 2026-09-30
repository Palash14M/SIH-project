package com.chakravyuh.smartinspection;

import android.app.Application;
import com.chakravyuh.smartinspection.db.AppDatabase;
import com.chakravyuh.smartinspection.util.PreferenceManager;

public class SmartInspectionApp extends Application {
    private static SmartInspectionApp instance;
    private AppDatabase database;
    private PreferenceManager preferenceManager;

    @Override
    public void onCreate() {
        super.onCreate();
        instance = this;
        database = AppDatabase.getInstance(this);
        preferenceManager = new PreferenceManager(this);
    }

    public static SmartInspectionApp getInstance() {
        return instance;
    }

    public AppDatabase getDatabase() {
        return database;
    }

    public PreferenceManager getPreferenceManager() {
        return preferenceManager;
    }
}
