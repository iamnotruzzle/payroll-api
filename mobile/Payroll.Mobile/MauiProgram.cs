using System.Text.Json;
using CommunityToolkit.Maui;
using Microsoft.Extensions.Logging;
using Payroll.Mobile.Services;
using Payroll.Mobile.Ui;
using Payroll.Mobile.ViewModels;
using Payroll.Mobile.Views;
using Refit;

namespace Payroll.Mobile;

public static class MauiProgram
{
    public static MauiApp CreateMauiApp()
    {
        var builder = MauiApp.CreateBuilder();
        builder
            .UseMauiApp<App>()
            .UseMauiCommunityToolkit()
            .ConfigureFonts(fonts =>
            {
                fonts.AddFont("OpenSans-Regular.ttf", "OpenSansRegular");
                fonts.AddFont("OpenSans-Semibold.ttf", "OpenSansSemibold");
            });

        NativeInput.Configure();

#if DEBUG
        builder.Logging.AddDebug();
#endif

        builder.Services.AddSingleton<TokenStorageService>();
        builder.Services.AddSingleton<IApiBaseUrlProvider, ApiBaseUrlProvider>();
        builder.Services.AddTransient<ApiBaseUrlHandler>();
        builder.Services.AddTransient<AuthHeaderHandler>();

        var jsonOptions = new JsonSerializerOptions
        {
            PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
            PropertyNameCaseInsensitive = true,
        };

        builder.Services
            .AddRefitGeneratedClient<IPayrollApi>(new RefitSettings
            {
                ContentSerializer = new SystemTextJsonContentSerializer(jsonOptions),
            })
            .ConfigureHttpClient(client =>
            {
                client.BaseAddress = new Uri("http://localhost/");
                client.Timeout = TimeSpan.FromSeconds(30);
            })
            .AddHttpMessageHandler<ApiBaseUrlHandler>()
            .AddHttpMessageHandler<AuthHeaderHandler>();

        builder.Services.AddTransient<AuthViewModel>();
        builder.Services.AddTransient<ClockViewModel>();
        builder.Services.AddTransient<LeavesViewModel>();
        builder.Services.AddTransient<PayslipsViewModel>();
        builder.Services.AddTransient<PayslipDetailViewModel>();

        builder.Services.AddTransient<LoginPage>();
        builder.Services.AddTransient<ClockPage>();
        builder.Services.AddTransient<LeavesPage>();
        builder.Services.AddTransient<PayslipsPage>();
        builder.Services.AddTransient<PayslipDetailPage>();

#if DEBUG
        builder.Services.AddTransient<Payroll.Mobile.Debug.SettingsViewModel>();
        builder.Services.AddTransient<Payroll.Mobile.Debug.SettingsPage>();
#endif

        return builder.Build();
    }
}
