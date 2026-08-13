namespace Payroll.Mobile.Ui;

public static class DarkTechMotion
{
    public static void Pulse(VisualElement view, string name, double peakScale = 1.02, uint duration = 2200)
    {
        view.AbortAnimation(name);
        view.Scale = 1;
        var animation = new Animation();
        animation.Add(0, 0.5, new Animation(v => view.Scale = v, 1, peakScale, Easing.SinOut));
        animation.Add(0.5, 1, new Animation(v => view.Scale = v, peakScale, 1, Easing.SinIn));
        animation.Commit(view, name, 16, duration, Easing.Linear, repeat: () => true);
    }

    public static void Bloom(VisualElement view, string name, uint duration = 2400, double peakScale = 1.55, double delayFraction = 0)
    {
        view.AbortAnimation(name);
        view.Scale = 1;
        view.Opacity = 0;

        var animation = new Animation();
        var start = Math.Clamp(delayFraction, 0, 0.6);
        if (start > 0)
        {
            animation.Add(0, start, new Animation(_ =>
            {
                view.Scale = 1;
                view.Opacity = 0;
            }));
        }

        animation.Add(start, 1, new Animation(v =>
        {
            view.Scale = 1 + ((peakScale - 1) * v);
            view.Opacity = 1 - v;
        }, 0, 1, Easing.CubicOut));

        animation.Commit(view, name, 16, duration, Easing.Linear, repeat: () => true);
    }

    public static void Stop(VisualElement view, string name, double opacity = 1)
    {
        view.AbortAnimation(name);
        view.Scale = 1;
        view.Opacity = opacity;
    }
}
