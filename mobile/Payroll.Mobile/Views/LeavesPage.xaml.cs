using Payroll.Mobile.ViewModels;

namespace Payroll.Mobile.Views;

public partial class LeavesPage : ContentPage
{
    public LeavesPage()
    {
        InitializeComponent();
        BindingContext = IPlatformApplication.Current!.Services.GetRequiredService<LeavesViewModel>();
    }

    protected override async void OnAppearing()
    {
        base.OnAppearing();
        if (BindingContext is LeavesViewModel viewModel)
        {
            await viewModel.LoadCommand.ExecuteAsync(null);
        }
    }
}
