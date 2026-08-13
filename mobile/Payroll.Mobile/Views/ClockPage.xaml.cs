using System.ComponentModel;
using Payroll.Mobile.Ui;
using Payroll.Mobile.ViewModels;

namespace Payroll.Mobile.Views;

public partial class ClockPage : ContentPage
{
    public ClockPage()
    {
        InitializeComponent();
        BindingContext = IPlatformApplication.Current!.Services.GetRequiredService<ClockViewModel>();
    }

    protected override async void OnAppearing()
    {
        base.OnAppearing();
        StartIdlePulse();
        if (BindingContext is ClockViewModel viewModel)
        {
            viewModel.PropertyChanged += OnViewModelPropertyChanged;
            await viewModel.LoadCommand.ExecuteAsync(null);
        }
    }

    protected override void OnDisappearing()
    {
        if (BindingContext is ClockViewModel viewModel)
        {
            viewModel.PropertyChanged -= OnViewModelPropertyChanged;
        }

        DarkTechMotion.Stop(HeroCard, "hero");
        DarkTechMotion.Stop(TimeInCard, "kpi-in");
        DarkTechMotion.Stop(TimeOutCard, "kpi-out");
        base.OnDisappearing();
    }

    private void OnViewModelPropertyChanged(object? sender, PropertyChangedEventArgs e)
    {
        if (e.PropertyName != nameof(ClockViewModel.IsBusy) || BindingContext is not ClockViewModel viewModel)
        {
            return;
        }

        if (viewModel.IsBusy)
        {
            DarkTechMotion.Pulse(HeroCard, "hero", 1.035, 900);
            DarkTechMotion.Pulse(TimeInCard, "kpi-in", 1.045, 800);
            DarkTechMotion.Pulse(TimeOutCard, "kpi-out", 1.045, 950);
        }
        else
        {
            StartIdlePulse();
        }
    }

    private void StartIdlePulse()
    {
        DarkTechMotion.Pulse(HeroCard, "hero", 1.012, 2800);
        DarkTechMotion.Pulse(TimeInCard, "kpi-in", 1.02, 2200);
        DarkTechMotion.Pulse(TimeOutCard, "kpi-out", 1.02, 2500);
    }
}
