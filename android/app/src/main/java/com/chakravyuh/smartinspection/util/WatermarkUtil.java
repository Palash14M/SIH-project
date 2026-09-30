package com.chakravyuh.smartinspection.util;

import android.content.Context;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.Rect;
import android.graphics.RectF;
import android.graphics.Typeface;
import java.io.InputStream;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

public class WatermarkUtil {

    /**
     * Burns translucent Government of India / MoSJE emblem logo watermark into the bottom-right corner
     * with ~40% opacity (alpha ~102/255), and date-time + coordinates into the bottom-left.
     */
    public static Bitmap applyWatermark(Bitmap source, Bitmap emblemLogo, double latitude, double longitude, float accuracy, boolean isMock) {
        if (source == null || source.getWidth() <= 0 || source.getHeight() <= 0) {
            return null;
        }

        int width = source.getWidth();
        int height = source.getHeight();

        Bitmap result = Bitmap.createBitmap(width, height, source.getConfig() != null ? source.getConfig() : Bitmap.Config.ARGB_8888);
        Canvas canvas = new Canvas(result);
        canvas.drawBitmap(source, 0, 0, null);

        // 1. Bottom-Right Emblem Logo Watermark (TASK 4: Government of India / MoSJE emblem logo)
        if (emblemLogo != null) {
            float targetLogoWidth = Math.max(72f, width * 0.14f);
            float aspectRatio = (float) emblemLogo.getHeight() / (float) emblemLogo.getWidth();
            float targetLogoHeight = targetLogoWidth * aspectRatio;

            float logoX = width - targetLogoWidth - (width * 0.04f);
            float logoY = height - targetLogoHeight - (height * 0.04f);

            Paint logoPaint = new Paint(Paint.ANTI_ALIAS_FLAG | Paint.FILTER_BITMAP_FLAG);
            logoPaint.setAlpha(102); // ~40% opacity (translucent burned into pixels)

            Rect srcRect = new Rect(0, 0, emblemLogo.getWidth(), emblemLogo.getHeight());
            RectF dstRect = new RectF(logoX, logoY, logoX + targetLogoWidth, logoY + targetLogoHeight);
            canvas.drawBitmap(emblemLogo, srcRect, dstRect, logoPaint);
        }

        // 2. Bottom-Left Telemetry: GPS coordinates, accuracy, and timestamp
        Paint telemetryPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
        telemetryPaint.setColor(Color.WHITE);
        telemetryPaint.setAlpha(180); // Higher readability for critical coordinates
        telemetryPaint.setTextSize(Math.max(16f, width * 0.022f));
        telemetryPaint.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.NORMAL));
        telemetryPaint.setShadowLayer(3.0f, 1.0f, 1.0f, Color.BLACK);

        SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss z", Locale.US);
        String timestamp = sdf.format(new Date());

        String coordLine = String.format(Locale.US, "LAT: %.6f | LNG: %.6f (±%.1fm)%s",
                latitude, longitude, accuracy, isMock ? " [MOCK]" : "");
        String timeLine = "TIME: " + timestamp;

        float telemetryX = width * 0.04f;
        float telemetryY2 = height - (height * 0.04f);
        float telemetryY1 = telemetryY2 - (telemetryPaint.getTextSize() * 1.3f);

        canvas.drawText(coordLine, telemetryX, telemetryY1, telemetryPaint);
        canvas.drawText(timeLine, telemetryX, telemetryY2, telemetryPaint);

        return result;
    }

    public static Bitmap applyWatermark(Bitmap source, Context context, double latitude, double longitude, float accuracy, boolean isMock) {
        Bitmap logo = null;
        if (context != null) {
            try {
                InputStream is = context.getAssets().open("watermark_logo.png");
                logo = BitmapFactory.decodeStream(is);
                is.close();
            } catch (Exception e) {
                try {
                    int resId = context.getResources().getIdentifier("watermark_logo", "drawable", context.getPackageName());
                    if (resId != 0) {
                        logo = BitmapFactory.decodeResource(context.getResources(), resId);
                    }
                } catch (Exception ignored) {}
            }
        }
        return applyWatermark(source, logo, latitude, longitude, accuracy, isMock);
    }

    public static Bitmap applyWatermark(Bitmap source, double latitude, double longitude, float accuracy, boolean isMock) {
        return applyWatermark(source, (Bitmap) null, latitude, longitude, accuracy, isMock);
    }
}
