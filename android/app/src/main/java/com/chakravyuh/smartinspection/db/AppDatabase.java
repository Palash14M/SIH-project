package com.chakravyuh.smartinspection.db;

import android.content.Context;
import androidx.room.Database;
import androidx.room.Room;
import androidx.room.RoomDatabase;
import com.chakravyuh.smartinspection.db.dao.InspectionDao;
import com.chakravyuh.smartinspection.db.entity.EvidenceEntity;
import com.chakravyuh.smartinspection.db.entity.InspectionEntity;

@Database(entities = {InspectionEntity.class, EvidenceEntity.class}, version = 1, exportSchema = false)
public abstract class AppDatabase extends RoomDatabase {
    private static volatile AppDatabase INSTANCE;

    public abstract InspectionDao inspectionDao();

    public static AppDatabase getInstance(Context context) {
        if (INSTANCE == null) {
            synchronized (AppDatabase.class) {
                if (INSTANCE == null) {
                    INSTANCE = Room.databaseBuilder(
                        context.getApplicationContext(),
                        AppDatabase.class,
                        "smart_inspection_local.db"
                    ).fallbackToDestructiveMigration().build();
                }
            }
        }
        return INSTANCE;
    }
}
