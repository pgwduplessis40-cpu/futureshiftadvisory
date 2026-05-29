import HomeController from './HomeController'
import ServicesController from './ServicesController'
import AboutController from './AboutController'
import FaqController from './FaqController'
import ContactController from './ContactController'
import SitemapController from './SitemapController'
const Public = {
    HomeController: Object.assign(HomeController, HomeController),
ServicesController: Object.assign(ServicesController, ServicesController),
AboutController: Object.assign(AboutController, AboutController),
FaqController: Object.assign(FaqController, FaqController),
ContactController: Object.assign(ContactController, ContactController),
SitemapController: Object.assign(SitemapController, SitemapController),
}

export default Public