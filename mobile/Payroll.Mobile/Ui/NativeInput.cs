using Microsoft.Maui.Handlers;

namespace Payroll.Mobile.Ui;

public static class NativeInput
{
    public static void Configure()
    {
        EntryHandler.Mapper.AppendToMapping("NativeField", (handler, _) =>
        {
#if ANDROID
            handler.PlatformView.BackgroundTintList =
                Android.Content.Res.ColorStateList.ValueOf(Android.Graphics.Color.Transparent);
#elif IOS || MACCATALYST
            handler.PlatformView.BorderStyle = UIKit.UITextBorderStyle.None;
#endif
        });
    }
}
